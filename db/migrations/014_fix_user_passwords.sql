-- Migration: Fix user passwords
-- Created: 2025-10-27
-- Description: Updates password hashes for test users with correct Argon2id hashes

DO $$
BEGIN
    -- Update straticus1@gmail.com password hash
    UPDATE users
    SET password_hash = '$argon2id$v=19$m=65536,t=4,p=1$dUpLVUFWTk9IcVd3Q0lKWg$lvXp0HVbHDP/6zRlDjMFyH3u1gGgZ9MOfNjZ9RXGop4'
    WHERE email = 'straticus1@gmail.com';

    RAISE NOTICE 'Updated password for straticus1@gmail.com';

    -- Update enterprise@veribits.com password hash
    UPDATE users
    SET password_hash = '$argon2id$v=19$m=65536,t=4,p=1$YnZHcHJBRGp0RnBESVBTTQ$Gyupash2d5fpuUaBnCCJNN1IXZxl4YQbOeXnt4pZP6w'
    WHERE email = 'enterprise@veribits.com';

    RAISE NOTICE 'Updated password for enterprise@veribits.com';

    RAISE NOTICE 'Password hashes updated successfully';
END $$;
