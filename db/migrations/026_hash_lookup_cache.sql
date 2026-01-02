-- Migration 026: Hash Lookup Cache
-- Caches successful hash lookups for faster future queries

CREATE TABLE IF NOT EXISTS hash_lookup_cache (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    hash VARCHAR(256) NOT NULL,
    hash_type VARCHAR(32) NOT NULL,
    plaintext TEXT NOT NULL,
    source VARCHAR(100),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    hit_count INTEGER DEFAULT 1,
    last_accessed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Unique constraint on hash+hash_type combination
CREATE UNIQUE INDEX IF NOT EXISTS idx_hash_cache_unique ON hash_lookup_cache(hash, hash_type);

-- Index for fast lookups
CREATE INDEX IF NOT EXISTS idx_hash_cache_hash ON hash_lookup_cache(hash);

-- Index for cache cleanup (LRU eviction)
CREATE INDEX IF NOT EXISTS idx_hash_cache_last_accessed ON hash_lookup_cache(last_accessed_at);

-- Function to update hit count and last_accessed_at on cache hit
CREATE OR REPLACE FUNCTION update_hash_cache_stats()
RETURNS TRIGGER AS $$
BEGIN
    -- This is handled in application code for better control
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

COMMENT ON TABLE hash_lookup_cache IS 'Caches successful hash lookups to reduce external API calls';
COMMENT ON COLUMN hash_lookup_cache.hit_count IS 'Number of times this cached entry has been accessed';
