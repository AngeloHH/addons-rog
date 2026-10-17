DO $$
DECLARE app_role text := current_setting('rog.app_role',true); t record;
BEGIN
  IF app_role IS NULL OR NOT EXISTS(SELECT 1 FROM pg_roles WHERE rolname=app_role) THEN
    RAISE EXCEPTION 'Set rog.app_role to an existing application role';
  END IF;
  EXECUTE format('GRANT USAGE ON SCHEMA rog_design TO %I',app_role);
  FOR t IN SELECT tablename FROM pg_tables WHERE schemaname='rog_design' AND tablename<>'schema_migrations' LOOP
    EXECUTE format('GRANT SELECT,INSERT,UPDATE,DELETE ON rog_design.%I TO %I',t.tablename,app_role);
  END LOOP;
  FOR t IN SELECT viewname AS name FROM pg_views WHERE schemaname='rog_design'
           UNION ALL SELECT matviewname FROM pg_matviews WHERE schemaname='rog_design' LOOP
    EXECUTE format('GRANT SELECT ON rog_design.%I TO %I',t.name,app_role);
  END LOOP;
  EXECUTE format('GRANT USAGE,SELECT ON ALL SEQUENCES IN SCHEMA rog_design TO %I',app_role);
  EXECUTE format('GRANT EXECUTE ON FUNCTION rog_design.normalize_name(text),rog_design.record_download(bigint,bigint,bytea,text,text) TO %I',app_role);
END $$;
