CREATE UNIQUE INDEX accounts_username_uq ON accounts (lower(username));
CREATE UNIQUE INDEX accounts_email_uq ON accounts (lower(email));

CREATE UNIQUE INDEX auth_session_secret_uq ON auth_tokens(secret_hash) WHERE purpose='session';
CREATE UNIQUE INDEX auth_verification_uq ON auth_tokens(account_id)
  WHERE purpose='verification' AND consumed_at IS NULL;
CREATE UNIQUE INDEX auth_invitation_uq ON auth_tokens(lower(email))
  WHERE purpose='invitation' AND consumed_at IS NULL;
CREATE INDEX auth_account_idx ON auth_tokens(account_id);
CREATE INDEX auth_expiry_idx ON auth_tokens(expires_at) WHERE consumed_at IS NULL;

CREATE INDEX assets_owner_idx ON assets(owner_id);
CREATE INDEX assets_expiry_idx ON assets(expires_at) WHERE expires_at IS NOT NULL;

CREATE INDEX addons_owner_idx ON addons(owner_id);
CREATE INDEX addons_details_gin ON addons USING gin ((details::jsonb) jsonb_path_ops);

CREATE UNIQUE INDEX requests_active_name_uq ON requests(normalize_name(addon_name), expansion_id)
  WHERE state IN ('open','volunteered','inQueue');
CREATE INDEX requests_author_idx ON requests(author_id);
CREATE INDEX requests_addon_idx ON requests(fulfilled_addon_id);
CREATE INDEX requests_state_idx ON requests(state, expansion_id, created_at DESC, id DESC);

CREATE UNIQUE INDEX submissions_version_uq ON submissions(addon_id, expansion_id, version_label)
  WHERE status IN ('pending','approved');
CREATE INDEX submissions_addon_idx ON submissions(addon_id, decided_at DESC, id DESC);
CREATE INDEX submissions_owner_idx ON submissions(owner_id, updated_at DESC);
CREATE INDEX submissions_request_idx ON submissions(request_id, status);
CREATE INDEX submissions_reviewer_idx ON submissions(reviewer_id);
CREATE INDEX submissions_queue_idx ON submissions(submitted_at, id) WHERE status='pending';
CREATE INDEX submissions_draft_idx ON submissions(updated_at) WHERE status='draft';

CREATE INDEX name_claims_addon_idx ON name_claims(addon_id);
CREATE INDEX name_claims_submission_idx ON name_claims(submission_id);

CREATE INDEX comments_addon_idx ON comments(addon_id, created_at DESC, id DESC);
CREATE INDEX comments_author_idx ON comments(author_id);
CREATE INDEX comments_rating_idx ON comments(addon_id, author_id, updated_at DESC, id DESC)
  WHERE rating IS NOT NULL;

CREATE INDEX interactions_addon_idx ON interactions(addon_id);
CREATE INDEX interactions_user_idx ON interactions(target_user_id,created_at);
CREATE INDEX interactions_request_idx ON interactions(request_id,kind);

CREATE UNIQUE INDEX reports_open_target_uq ON reports(type,addon_id,submission_id,comment_id)
  NULLS NOT DISTINCT WHERE closed_at IS NULL;
CREATE INDEX reports_addon_idx ON reports(addon_id);
CREATE INDEX reports_submission_idx ON reports(submission_id);
CREATE INDEX reports_comment_idx ON reports(comment_id);

CREATE INDEX report_entries_reporter_idx ON report_entries(reporter_id);

CREATE INDEX messages_sender_idx ON messages(sender_id);
CREATE INDEX messages_recipient_idx ON messages(recipient_id);
CREATE INDEX messages_parent_idx ON messages(parent_id);
CREATE INDEX messages_queue_idx ON messages(available_at,id) WHERE status IN ('queued','processing');
CREATE UNIQUE INDEX messages_delivery_uq ON messages((data->>'dedupeKey'))
  WHERE data->>'dedupeKey' IS NOT NULL;

CREATE INDEX asset_links_asset_idx ON asset_links(asset_id);
CREATE INDEX asset_links_addon_idx ON asset_links(addon_id);
CREATE INDEX asset_links_message_idx ON asset_links(message_id);

CREATE INDEX events_time_idx ON events(occurred_at DESC,id DESC);
CREATE INDEX events_actor_idx ON events(actor_id,occurred_at DESC);
CREATE INDEX events_kind_idx ON events(kind,occurred_at DESC,id DESC);
CREATE INDEX events_submission_idx ON events(submission_id);
CREATE INDEX events_download_dedupe_idx ON events(submission_id,client_key,occurred_at DESC)
  WHERE kind='download' AND data @> '{"counted":true}';

CREATE VIEW published_versions AS
SELECT s.id, s.addon_id, s.owner_id, s.expansion_id, s.version_label,
       s.fields, s.decided_at AS released_at, l.asset_id
FROM submissions s JOIN addons a ON a.id=s.addon_id
JOIN asset_links l ON l.submission_id=s.id AND l.slot='zip'
JOIN assets f ON f.id=l.asset_id
WHERE s.status='approved' AND s.withdrawn_at IS NULL AND a.withdrawn_at IS NULL
  AND f.status IN ('clean','warning');

CREATE VIEW latest_versions AS
SELECT DISTINCT ON (addon_id,expansion_id) * FROM published_versions
ORDER BY addon_id,expansion_id,released_at DESC,id DESC;

CREATE VIEW current_ratings AS
SELECT r.* FROM (
  SELECT DISTINCT ON (addon_id,author_id) * FROM comments WHERE rating IS NOT NULL
  ORDER BY addon_id,author_id,updated_at DESC,id DESC
) r WHERE r.withdrawn_at IS NULL;

CREATE VIEW addon_ratings AS
SELECT addon_id, round(avg(rating),1) AS rating, count(*) AS votes
FROM current_ratings GROUP BY addon_id;

CREATE VIEW account_state AS
SELECT a.*,
  CASE WHEN access_level=2 THEN 'founder'
       WHEN access_level=1 THEN 'moderator'
       WHEN EXISTS (SELECT 1 FROM addons d JOIN published_versions v ON v.addon_id=d.id
                    WHERE d.owner_id=a.id) THEN 'author' ELSE 'player' END AS role,
  CASE WHEN banned_at IS NOT NULL THEN 'banned'
       WHEN suspended_until > now() THEN 'suspended' ELSE 'active' END AS status
FROM accounts a;

CREATE VIEW counted_downloads AS
SELECT * FROM events WHERE kind='download' AND data @> '{"counted":true}';

CREATE VIEW submission_expiry AS
SELECT id, CASE WHEN status='draft' THEN updated_at + interval '30 days' END AS expires_at
FROM submissions;

CREATE FUNCTION guard_submission() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.owner_id<>OLD.owner_id OR NEW.kind<>OLD.kind THEN
    RAISE EXCEPTION 'owner/kind are immutable';
  END IF;
  IF OLD.status<>'draft' AND
     ((NEW.fields::jsonb - 'decision') IS DISTINCT FROM (OLD.fields::jsonb - 'decision')
      OR NEW.version_label IS DISTINCT FROM OLD.version_label
      OR NEW.expansion_id IS DISTINCT FROM OLD.expansion_id
      OR NEW.submitted_at IS DISTINCT FROM OLD.submitted_at) THEN
    RAISE EXCEPTION 'submitted content is immutable';
  END IF;
  IF (NEW.fields->'decision') IS DISTINCT FROM (OLD.fields->'decision') AND NOT (
      OLD.status='pending' AND NEW.status IN ('approved','rejected')) THEN
    RAISE EXCEPTION 'decision can only be written when deciding a pending submission';
  END IF;
  IF OLD.status IN ('approved','rejected') AND
      (NEW.decided_at IS DISTINCT FROM OLD.decided_at OR NEW.reviewer_id IS DISTINCT FROM OLD.reviewer_id) THEN
    RAISE EXCEPTION 'decision metadata is immutable';
  END IF;
  IF NEW.created_at IS DISTINCT FROM OLD.created_at THEN
    RAISE EXCEPTION 'creation date is immutable';
  END IF;
  IF NEW.status<>OLD.status AND NOT (
      (OLD.status='draft' AND NEW.status='pending') OR
      (OLD.status='pending' AND NEW.status IN ('approved','rejected'))) THEN
    RAISE EXCEPTION 'invalid submission transition';
  END IF;
  IF OLD.addon_id IS DISTINCT FROM NEW.addon_id AND NOT (
       OLD.status='draft' OR (OLD.status='pending' AND NEW.status='approved'
                             AND OLD.kind='addon' AND OLD.addon_id IS NULL)) THEN
    RAISE EXCEPTION 'addon link is immutable after submission';
  END IF;
  NEW.revision := OLD.revision+1;
  NEW.updated_at := greatest(clock_timestamp(), OLD.updated_at + interval '1 microsecond');
  RETURN NEW;
END $$;
CREATE TRIGGER guard_submission BEFORE UPDATE ON submissions
  FOR EACH ROW EXECUTE FUNCTION guard_submission();

CREATE FUNCTION guard_slug() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.slug<>OLD.slug THEN RAISE EXCEPTION 'slug is immutable'; END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER guard_slug BEFORE UPDATE ON addons FOR EACH ROW EXECUTE FUNCTION guard_slug();

CREATE FUNCTION guard_request() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.state<>OLD.state AND NOT (
    (OLD.state='open' AND NEW.state IN ('volunteered','inQueue','fulfilled','closed')) OR
    (OLD.state='volunteered' AND NEW.state IN ('open','inQueue','fulfilled','closed')) OR
    (OLD.state='inQueue' AND NEW.state IN ('open','volunteered','fulfilled'))) THEN
    RAISE EXCEPTION 'invalid request transition';
  END IF;
  NEW.updated_at:=clock_timestamp();
  RETURN NEW;
END $$;
CREATE TRIGGER guard_request BEFORE UPDATE ON requests FOR EACH ROW EXECUTE FUNCTION guard_request();

CREATE FUNCTION guard_vote() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE r bigint; k text; s text;
BEGIN
  IF TG_OP='DELETE' THEN r:=OLD.request_id; k:=OLD.kind; ELSE r:=NEW.request_id; k:=NEW.kind; END IF;
  IF TG_OP='UPDATE' THEN RAISE EXCEPTION 'interactions use INSERT/DELETE, not UPDATE'; END IF;
  IF k='request_vote' THEN
    SELECT state INTO s FROM requests WHERE id=r FOR SHARE;
    IF s IN ('fulfilled','closed') THEN RAISE EXCEPTION 'request votes are frozen'; END IF;
  END IF;
  IF TG_OP='DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
CREATE TRIGGER guard_vote BEFORE INSERT OR UPDATE OR DELETE ON interactions
  FOR EACH ROW EXECUTE FUNCTION guard_vote();

ALTER FUNCTION guard_submission() SET search_path=rog_design,pg_catalog;
ALTER FUNCTION guard_slug() SET search_path=rog_design,pg_catalog;
ALTER FUNCTION guard_request() SET search_path=rog_design,pg_catalog;
ALTER FUNCTION guard_vote() SET search_path=rog_design,pg_catalog;

CREATE FUNCTION record_download(p_version bigint, p_actor bigint, p_client_key bytea,
                               p_city text DEFAULT NULL, p_pack text DEFAULT NULL)
RETURNS bigint LANGUAGE plpgsql SET search_path=rog_design,pg_catalog AS $$
DECLARE t timestamptz; n bigint; counted boolean;
BEGIN
  IF p_client_key IS NULL THEN RAISE EXCEPTION 'client HMAC required'; END IF;
  PERFORM pg_advisory_xact_lock(hashtextextended(p_version::text || ':' || encode(p_client_key,'hex'),0));
  IF NOT EXISTS(SELECT 1 FROM published_versions WHERE id=p_version) THEN
    RAISE EXCEPTION 'version unavailable';
  END IF;
  t:=clock_timestamp();
  counted:=NOT EXISTS(SELECT 1 FROM counted_downloads WHERE submission_id=p_version
    AND client_key=p_client_key AND occurred_at>t-interval '24 hours');
  INSERT INTO events(kind,actor_id,submission_id,client_key,occurred_at,data)
  VALUES ('download',p_actor,p_version,p_client_key,t,
    jsonb_build_object('counted',counted,'city',p_city,'packId',p_pack)) RETURNING id INTO n;
  RETURN n;
END $$;

REVOKE ALL ON SCHEMA rog_design FROM PUBLIC;
REVOKE ALL ON ALL TABLES IN SCHEMA rog_design FROM PUBLIC;
REVOKE EXECUTE ON ALL FUNCTIONS IN SCHEMA rog_design FROM PUBLIC;

CREATE MATERIALIZED VIEW catalog_metrics AS
WITH downloads AS(SELECT s.addon_id,count(*) AS downloads FROM counted_downloads e JOIN submissions s ON s.id=e.submission_id GROUP BY s.addon_id)
SELECT a.id AS addon_id,coalesce(d.downloads,0)::bigint AS downloads,r.rating,coalesce(r.votes,0)::bigint AS votes,now() AS refreshed_at FROM addons a LEFT JOIN downloads d ON d.addon_id=a.id LEFT JOIN addon_ratings r ON r.addon_id=a.id;
CREATE UNIQUE INDEX catalog_metrics_addon_uq ON catalog_metrics(addon_id);
CREATE INDEX catalog_metrics_downloads_idx ON catalog_metrics(downloads DESC,addon_id);
CREATE INDEX events_rate_idx ON events((data->>'key'),occurred_at DESC) WHERE kind='rateLimit';
REVOKE ALL ON catalog_metrics FROM PUBLIC;
