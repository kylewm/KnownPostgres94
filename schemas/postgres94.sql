--
-- Base Known schema
--

drop table config;
drop table entities;
drop table reader;
drop table versions;
drop table session;
drop function if exists entities_populate_search();

--
-- Table structure for table config
--

create table if not exists config (
  _id varchar(32) not null primary key,
  uuid varchar(255) not null unique,
  jdoc jsonb,
  search tsvector
);
create index on config (uuid);
create index on config using gin(search);

-- --------------------------------------------------------

--
-- Table structure for table entities
--

create table if not exists entities (
  _id varchar(32) not null primary key,
  uuid varchar(255) not null unique,
  jdoc jsonb,
  search tsvector
);

create index on entities (uuid);
create index on entities using gin(( jdoc -> 'entity_subtype' ));
create index on entities using gin(( jdoc -> 'created' ));
create index on entities using gin(( jdoc -> 'owner' ));
create index on entities using gin(( jdoc -> 'object' ));
create index on entities using gin(search);

-- CREATE FUNCTION entities_populate_search() RETURNS trigger AS $$
-- begin
--   new.search :=
--     setweight(to_tsvector('pg_catalog.english', coalesce(new.jdoc->>'title','')), 'A') ||
--     setweight(to_tsvector('pg_catalog.english', coalesce(new.jdoc->>'tags','')), 'A') ||
--     setweight(to_tsvector('pg_catalog.english', coalesce(new.jdoc->>'description','')), 'B') ||
--     setweight(to_tsvector('pg_catalog.english', coalesce(new.jdoc->>'body','')), 'B');
--   return new;
-- end
-- $$ LANGUAGE plpgsql;

-- CREATE TRIGGER entities_search_trigger BEFORE INSERT OR UPDATE
--     ON entities FOR EACH ROW EXECUTE PROCEDURE entities_populate_search();

-- --------------------------------------------------------

--
-- Table structure for table reader
--

create table if not exists reader (
  _id varchar(32) not null primary key,
  uuid varchar(255) not null unique,
  jdoc jsonb,
  search tsvector
);

create index on reader (uuid);
create index on reader using gin(search);


-- --------------------------------------------------------

--
-- Table structure for table versions
--

create table if not exists versions (
  label varchar(32) not null primary key,
  value varchar(10) not null
);

delete from versions where label = 'schema';
insert into versions values('schema', '20160220');

--
-- Session handling table
--

create table if not exists session (
    session_id varchar(255) not null primary key,
    session_value text not null,
    session_time integer not null
);
