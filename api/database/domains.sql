CREATE DOMAIN object_json AS jsonb
  CHECK (jsonb_typeof(VALUE) = 'object');
CREATE DOMAIN expansion_code AS text
  CHECK (VALUE IN ('wotlk','mop','wod','bfa','sl','df','tww'));
CREATE DOMAIN category_code AS text
  CHECK (VALUE IN ('pve','pvp','dps','healing','tank','gui','map','professions'));

CREATE FUNCTION normalize_name(text) RETURNS text
LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE
AS $$ SELECT regexp_replace(lower(normalize($1, NFKC)), '[^[:alnum:]]', '', 'g') $$;
