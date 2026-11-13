-- Local and CI database layout. Production uses the same three roles with strong, unique passwords.
--  sp_owner    owns the schema, runs migrations only
--  sp_app      the web app: not owner, no BYPASSRLS, so row-level security always applies
--  sp_platform super-admin panel: BYPASSRLS
CREATE ROLE sp_owner LOGIN PASSWORD 'owner_pw';
CREATE ROLE sp_app LOGIN PASSWORD 'app_pw';
CREATE ROLE sp_platform LOGIN PASSWORD 'platform_pw' BYPASSRLS;
CREATE DATABASE staffing OWNER sp_owner;
CREATE DATABASE staffing_test OWNER sp_owner;
