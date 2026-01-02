# How to Run Database Migrations via AWS SSM

## Quick Reference

Since the RDS database is in a private VPC and not publicly accessible, use AWS Systems Manager (SSM) to run migrations from an EC2 instance that has network access.

## Method: SSM Send-Command with JSON Parameters

### Step 1: Create a JSON parameters file

```json
{
  "InstanceIds": ["i-0dfa3bdad3e032105"],
  "DocumentName": "AWS-RunShellScript",
  "Parameters": {
    "commands": [
      "cat > /tmp/migration.sql << 'SQLEOF'",
      "-- Your SQL statements here (one per line, or use \\n for newlines)",
      "CREATE TABLE IF NOT EXISTS my_table (id SERIAL PRIMARY KEY);",
      "SQLEOF",
      "PGPASSWORD=NiteText2025!SecureProd psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com -U nitetext -d veribits -f /tmp/migration.sql 2>&1"
    ]
  }
}
```

### Step 2: Run the command

```bash
aws ssm send-command \
  --cli-input-json file:///path/to/params.json \
  --output text \
  --query 'Command.CommandId'
```

### Step 3: Check the results

```bash
sleep 10  # Wait for command to complete
aws ssm get-command-invocation \
  --command-id YOUR_COMMAND_ID \
  --instance-id i-0dfa3bdad3e032105 \
  --query '[Status,StandardOutputContent]' \
  --output text
```

## Important Notes

### SSM-Enabled Instances

The following instances have SSM agent installed and can reach the database:
- **i-0dfa3bdad3e032105** (working instance, use this one)
- i-0af78b6091919edd0
- i-0d43303e973afd911

### Transaction Handling

- **DO NOT** wrap migrations in BEGIN/COMMIT if tables might already exist
- Use `CREATE TABLE IF NOT EXISTS` for idempotent migrations
- Let each statement succeed/fail independently

### JSON Escaping Rules

1. **Passwords**: Use `NiteText2025!SecureProd` (exclamation marks don't need escaping inside double quotes)
2. **Single quotes in SQL**: Escape as `'\''` in heredoc, or use JSON string escapes
3. **Backslashes**: Avoid `\d` or other psql meta-commands (they don't work in `-c` mode)
4. **Heredoc delimiter**: Always use `'SQLEOF'` (single quotes prevent variable expansion)

### Common Issues and Solutions

**Problem**: `Invalid JSON: Invalid \escape`
- **Solution**: Don't use backslashes in JSON strings. Use heredoc to pass SQL to psql

**Problem**: Transaction rolled back due to existing table
- **Solution**: Remove BEGIN/COMMIT, use IF NOT EXISTS clauses

**Problem**: `Instances not in a valid state`
- **Solution**: Instance might be terminated or SSM agent not running. Check instance status:
  ```bash
  aws ssm describe-instance-information \
    --query 'InstanceInformationList[*].[InstanceId,PingStatus]' \
    --output table
  ```

**Problem**: `Connection refused` to database
- **Solution**: Use SSM instance that's in the same VPC (vpc-0c1b813880b3982a5) as the RDS

## Example: Complete Migration Workflow

```bash
# 1. Create migration SQL file
cat > /tmp/my-migration.json << 'EOF'
{
  "InstanceIds": ["i-0dfa3bdad3e032105"],
  "DocumentName": "AWS-RunShellScript",
  "Parameters": {
    "commands": [
      "cat > /tmp/migration.sql << 'SQLEOF'",
      "CREATE TABLE IF NOT EXISTS my_new_table (id SERIAL PRIMARY KEY, name VARCHAR(255));",
      "CREATE INDEX IF NOT EXISTS idx_my_new_table_name ON my_new_table(name);",
      "SQLEOF",
      "PGPASSWORD=NiteText2025!SecureProd psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com -U nitetext -d veribits -f /tmp/migration.sql 2>&1"
    ]
  }
}
EOF

# 2. Execute migration
CMD_ID=$(aws ssm send-command \
  --cli-input-json file:///tmp/my-migration.json \
  --output text \
  --query 'Command.CommandId')

echo "Command ID: $CMD_ID"

# 3. Wait and check results
sleep 10
aws ssm get-command-invocation \
  --command-id $CMD_ID \
  --instance-id i-0dfa3bdad3e032105 \
  --query '[Status,StandardOutputContent]' \
  --output text
```

## Database Connection Details

- **Host**: nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com
- **User**: nitetext
- **Database**: veribits
- **Password**: NiteText2025!SecureProd
- **VPC**: vpc-0c1b813880b3982a5 (private, not publicly accessible)
- **Security Group**: sg-011e3c8ac8f73858b

## Successfully Deployed Migrations

### Enterprise Features (October 2025)

**Migration 020**: Pro Subscriptions
- Table: `pro_licenses`
- Test license: `VBPRO-DEV-TEST-0000000000000000`

**Migration 021**: OAuth2 & Webhooks
- Tables: `oauth_clients`, `oauth_authorization_codes`, `oauth_access_tokens`, `oauth_refresh_tokens`, `webhook_deliveries`
- Test client: `vb_zapier_test_client_0000000000`

**Migration 022**: Malware Detonation
- Tables: `malware_submissions`, `malware_analysis_results`, `malware_screenshots`

All tables use **UUID** for `user_id` foreign keys (not INTEGER).

## Verification Commands

```bash
# List all enterprise tables
aws ssm send-command \
  --instance-ids i-0dfa3bdad3e032105 \
  --document-name AWS-RunShellScript \
  --parameters '{"commands":["PGPASSWORD=NiteText2025!SecureProd psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com -U nitetext -d veribits -t -c \"SELECT tablename FROM pg_tables WHERE schemaname = '\''public'\'' ORDER BY tablename;\" 2>&1"]}' \
  --output text \
  --query 'Command.CommandId'
```
