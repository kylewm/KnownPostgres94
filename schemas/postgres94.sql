--
-- Base Known schema
--

--
-- Table structure for table config
--

CREATE TABLE IF NOT EXISTS config (
  _id varchar(32) NOT NULL PRIMARY KEY,
  uuid varchar(255) NOT NULL UNIQUE,
  jdoc jsonb
);
CREATE INDEX ON config (uuid);

-- --------------------------------------------------------

--
-- Table structure for table entities
--

CREATE TABLE IF NOT EXISTS entities (
  _id varchar(32) NOT NULL PRIMARY KEY,
  uuid varchar(255) NOT NULL UNIQUE,
  jdoc jsonb
);

CREATE INDEX ON entities (uuid);
CREATE INDEX ON entities USING GIN (( jdoc -> 'entity_subtype' ));
CREATE INDEX ON entities USING GIN (( jdoc -> 'created' ));
CREATE INDEX ON entities USING GIN (( jdoc -> 'owner' ));
CREATE INDEX ON entities USING GIN (( jdoc -> 'object' ));


-- FULL TEXT ?



-- --------------------------------------------------------

--
-- Table structure for table reader
--

CREATE TABLE IF NOT EXISTS reader (
  _id varchar(32) NOT NULL PRIMARY KEY,
  uuid varchar(255) NOT NULL UNIQUE,
  jdoc jsonb
);

CREATE INDEX ON reader (uuid);


-- --------------------------------------------------------

--
-- Table structure for table versions
--

CREATE TABLE IF NOT EXISTS versions (
  label varchar(32) NOT NULL PRIMARY KEY,
  value varchar(10) NOT NULL
);

DELETE FROM versions WHERE label = 'schema';
INSERT INTO versions VALUES('schema', '20160220');

--
-- Session handling table
--

CREATE TABLE IF NOT EXISTS session (
    session_id varchar(255) NOT NULL PRIMARY KEY,
    session_value text NOT NULL,
    session_time integer NOT NULL
);
