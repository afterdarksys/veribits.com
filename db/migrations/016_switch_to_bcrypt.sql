-- Migration 016: Switch to BCrypt for password hashing
-- Created: 2025-10-27
-- Description: Update straticus1 password to use BCrypt instead of Argon2id
-- Password: TestPassword123!

DO $$
BEGIN
    -- BCrypt hash for TestPassword123!
    -- Generated with: password_hash('TestPassword123!', PASSWORD_BCRYPT)
    UPDATE users
    SET password_hash = '$2y$12$eKJCykdGXuNZ.k/lJQtHF.f51GG/Uetdhuqm0BU6cGYAlEYkCfAG2'
    WHERE email = 'straticus1@gmail.com';

    RAISE NOTICE 'Updated straticus1@gmail.com to use BCrypt';
END $$;
