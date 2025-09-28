-- PostgreSQL initialization script for OpenWeave
-- This script runs when the PostgreSQL container starts for the first time

-- Create extensions if needed
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Grant necessary permissions to the application user
GRANT ALL PRIVILEGES ON DATABASE openweave TO openweave_user;

-- Set default permissions for future objects
ALTER DEFAULT PRIVILEGES GRANT ALL ON TABLES TO openweave_user;
ALTER DEFAULT PRIVILEGES GRANT ALL ON SEQUENCES TO openweave_user;
ALTER DEFAULT PRIVILEGES GRANT ALL ON FUNCTIONS TO openweave_user;
