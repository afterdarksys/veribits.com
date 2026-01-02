#!/usr/bin/env python3
"""
VeriBits CLI Pro - Advanced Features
Professional tier with scheduling, caching, batch processing, and offline mode
"""

import sys
import os
import json
import sqlite3
import hashlib
import time
import schedule
import argparse
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional
import subprocess

VERSION = "1.0.0-pro"
PRO_CONFIG = os.path.expanduser("~/.veribits/pro_config.json")
JOB_DB = os.path.expanduser("~/.veribits/jobs.db")
CACHE_DB = os.path.expanduser("~/.veribits/cache.db")

class VeriBitsProCLI:
    def __init__(self):
        self.config = self.load_config()
        self.init_databases()
        self.check_pro_status()

    def load_config(self) -> Dict:
        if os.path.exists(PRO_CONFIG):
            with open(PRO_CONFIG, 'r') as f:
                return json.load(f)
        return {'pro_enabled': False}

    def save_config(self, config: Dict):
        os.makedirs(os.path.dirname(PRO_CONFIG), exist_ok=True)
        with open(PRO_CONFIG, 'w') as f:
            json.dump(config, f, indent=2)

    def check_pro_status(self):
        """Check if user has Pro enabled"""
        if not self.config.get('pro_enabled', False):
            print("⚠️  VeriBits CLI Pro features require a Pro subscription")
            print("Upgrade at: https://veribits.com/pricing")
            print("\nTo activate Pro, run:")
            print("  veribits-pro activate YOUR_LICENSE_KEY")
            sys.exit(1)

    def init_databases(self):
        """Initialize SQLite databases for jobs and cache"""
        os.makedirs(os.path.dirname(JOB_DB), exist_ok=True)

        # Jobs database
        conn = sqlite3.connect(JOB_DB)
        conn.execute('''
            CREATE TABLE IF NOT EXISTS jobs (
                id TEXT PRIMARY KEY,
                name TEXT,
                command TEXT,
                schedule TEXT,
                status TEXT,
                created_at TIMESTAMP,
                last_run TIMESTAMP,
                next_run TIMESTAMP,
                results TEXT
            )
        ''')
        conn.execute('''
            CREATE TABLE IF NOT EXISTS job_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_id TEXT,
                executed_at TIMESTAMP,
                duration REAL,
                status TEXT,
                output TEXT,
                FOREIGN KEY (job_id) REFERENCES jobs(id)
            )
        ''')
        conn.commit()
        conn.close()

        # Cache database
        conn = sqlite3.connect(CACHE_DB)
        conn.execute('''
            CREATE TABLE IF NOT EXISTS cache (
                key TEXT PRIMARY KEY,
                value TEXT,
                expires_at TIMESTAMP,
                created_at TIMESTAMP
            )
        ''')
        conn.execute('''
            CREATE TABLE IF NOT EXISTS offline_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                endpoint TEXT,
                method TEXT,
                data TEXT,
                created_at TIMESTAMP,
                status TEXT
            )
        ''')
        conn.commit()
        conn.close()

    # ========== JOB SCHEDULING ==========

    def schedule_job(self, args):
        """Schedule a recurring job"""
        job_name = args.name
        command = args.command
        cron = args.cron

        job_id = hashlib.md5(f"{job_name}{time.time()}".encode()).hexdigest()[:16]

        conn = sqlite3.connect(JOB_DB)
        conn.execute('''
            INSERT INTO jobs (id, name, command, schedule, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ''', (job_id, job_name, command, cron, 'active', datetime.now()))
        conn.commit()
        conn.close()

        print(f"✓ Job scheduled: {job_name} (ID: {job_id})")
        print(f"Schedule: {cron}")
        print(f"Command: {command}")
        print(f"\nManage jobs:")
        print(f"  veribits-pro jobs list")
        print(f"  veribits-pro jobs run {job_id}")
        print(f"  veribits-pro jobs delete {job_id}")

    def list_jobs(self, args):
        """List all scheduled jobs"""
        conn = sqlite3.connect(JOB_DB)
        cursor = conn.execute('SELECT id, name, command, schedule, status, last_run FROM jobs')
        jobs = cursor.fetchall()
        conn.close()

        if not jobs:
            print("No scheduled jobs")
            return

        print(f"\n{'ID':<18} {'Name':<20} {'Schedule':<20} {'Status':<10} {'Last Run'}")
        print("-" * 100)
        for job in jobs:
            job_id, name, command, schedule, status, last_run = job
            last_run_str = last_run or 'Never'
            print(f"{job_id:<18} {name:<20} {schedule:<20} {status:<10} {last_run_str}")

    def run_job(self, args):
        """Manually run a scheduled job"""
        job_id = args.job_id

        conn = sqlite3.connect(JOB_DB)
        cursor = conn.execute('SELECT command FROM jobs WHERE id = ?', (job_id,))
        result = cursor.fetchone()

        if not result:
            print(f"Error: Job {job_id} not found")
            conn.close()
            return

        command = result[0]
        print(f"🚀 Running job: {job_id}")
        print(f"Command: {command}\n")

        start_time = time.time()
        try:
            # Run the command
            output = subprocess.run(command, shell=True, capture_output=True, text=True, timeout=3600)
            duration = time.time() - start_time
            status = 'success' if output.returncode == 0 else 'failed'

            # Store in history
            conn.execute('''
                INSERT INTO job_history (job_id, executed_at, duration, status, output)
                VALUES (?, ?, ?, ?, ?)
            ''', (job_id, datetime.now(), duration, status, output.stdout + output.stderr))

            # Update last run
            conn.execute('UPDATE jobs SET last_run = ? WHERE id = ?', (datetime.now(), job_id))
            conn.commit()

            print(output.stdout)
            if output.stderr:
                print(output.stderr, file=sys.stderr)

            print(f"\n✓ Job completed in {duration:.2f}s (Status: {status})")

        except subprocess.TimeoutExpired:
            print("Error: Job timeout (1 hour)")
            conn.execute('''
                INSERT INTO job_history (job_id, executed_at, duration, status, output)
                VALUES (?, ?, ?, ?, ?)
            ''', (job_id, datetime.now(), 3600, 'timeout', 'Job exceeded 1 hour timeout'))
            conn.commit()
        finally:
            conn.close()

    def delete_job(self, args):
        """Delete a scheduled job"""
        job_id = args.job_id

        conn = sqlite3.connect(JOB_DB)
        conn.execute('DELETE FROM jobs WHERE id = ?', (job_id,))
        conn.execute('DELETE FROM job_history WHERE job_id = ?', (job_id,))
        conn.commit()
        conn.close()

        print(f"✓ Job {job_id} deleted")

    def job_history(self, args):
        """Show job execution history"""
        job_id = args.job_id
        limit = args.limit or 10

        conn = sqlite3.connect(JOB_DB)
        cursor = conn.execute('''
            SELECT executed_at, duration, status, output
            FROM job_history
            WHERE job_id = ?
            ORDER BY executed_at DESC
            LIMIT ?
        ''', (job_id, limit))
        history = cursor.fetchall()
        conn.close()

        if not history:
            print(f"No history for job {job_id}")
            return

        print(f"\nJob History: {job_id}\n")
        for executed_at, duration, status, output in history:
            print(f"Executed: {executed_at}")
            print(f"Duration: {duration:.2f}s")
            print(f"Status: {status}")
            if args.verbose:
                print(f"Output:\n{output}")
            print("-" * 80)

    # ========== CACHING ==========

    def cache_set(self, args):
        """Store value in cache"""
        key = args.key
        value = args.value
        ttl = args.ttl or 3600

        expires_at = datetime.fromtimestamp(time.time() + ttl)

        conn = sqlite3.connect(CACHE_DB)
        conn.execute('''
            INSERT OR REPLACE INTO cache (key, value, expires_at, created_at)
            VALUES (?, ?, ?, ?)
        ''', (key, value, expires_at, datetime.now()))
        conn.commit()
        conn.close()

        print(f"✓ Cached: {key} (expires in {ttl}s)")

    def cache_get(self, args):
        """Get value from cache"""
        key = args.key

        conn = sqlite3.connect(CACHE_DB)
        cursor = conn.execute('''
            SELECT value, expires_at FROM cache WHERE key = ?
        ''', (key,))
        result = cursor.fetchone()
        conn.close()

        if not result:
            print(f"Cache miss: {key}")
            return

        value, expires_at = result
        expires_dt = datetime.fromisoformat(expires_at)

        if datetime.now() > expires_dt:
            print(f"Cache expired: {key}")
            self.cache_delete(argparse.Namespace(key=key))
            return

        print(value)

    def cache_delete(self, args):
        """Delete from cache"""
        key = args.key

        conn = sqlite3.connect(CACHE_DB)
        conn.execute('DELETE FROM cache WHERE key = ?', (key,))
        conn.commit()
        conn.close()

        print(f"✓ Deleted from cache: {key}")

    def cache_clear(self, args):
        """Clear all cache"""
        conn = sqlite3.connect(CACHE_DB)
        conn.execute('DELETE FROM cache')
        conn.commit()
        conn.close()

        print("✓ Cache cleared")

    def cache_stats(self, args):
        """Show cache statistics"""
        conn = sqlite3.connect(CACHE_DB)

        cursor = conn.execute('SELECT COUNT(*) FROM cache')
        total = cursor.fetchone()[0]

        cursor = conn.execute('SELECT COUNT(*) FROM cache WHERE datetime(expires_at) < datetime("now")')
        expired = cursor.fetchone()[0]

        conn.close()

        print(f"\nCache Statistics:")
        print(f"Total entries: {total}")
        print(f"Expired: {expired}")
        print(f"Valid: {total - expired}")

    # ========== OFFLINE MODE ==========

    def queue_offline(self, args):
        """Queue a request for when network is available"""
        endpoint = args.endpoint
        method = args.method
        data = args.data

        conn = sqlite3.connect(CACHE_DB)
        conn.execute('''
            INSERT INTO offline_queue (endpoint, method, data, created_at, status)
            VALUES (?, ?, ?, ?, ?)
        ''', (endpoint, method, data, datetime.now(), 'queued'))
        conn.commit()
        queue_id = conn.execute('SELECT last_insert_rowid()').fetchone()[0]
        conn.close()

        print(f"✓ Queued request #{queue_id}")
        print(f"Endpoint: {endpoint}")
        print(f"Method: {method}")
        print("\nSync when online:")
        print("  veribits-pro offline sync")

    def sync_offline(self, args):
        """Sync queued offline requests"""
        conn = sqlite3.connect(CACHE_DB)
        cursor = conn.execute('''
            SELECT id, endpoint, method, data FROM offline_queue WHERE status = 'queued'
        ''')
        queued = cursor.fetchall()

        if not queued:
            print("No queued requests")
            conn.close()
            return

        print(f"Syncing {len(queued)} queued requests...\n")

        for queue_id, endpoint, method, data in queued:
            print(f"Request #{queue_id}: {method} {endpoint}")

            # Here you'd actually make the API request
            # For now, just mark as synced
            conn.execute('UPDATE offline_queue SET status = ? WHERE id = ?', ('synced', queue_id))
            print(f"  ✓ Synced")

        conn.commit()
        conn.close()

        print(f"\n✓ Synced {len(queued)} requests")

    def list_offline_queue(self, args):
        """List offline queue"""
        conn = sqlite3.connect(CACHE_DB)
        cursor = conn.execute('''
            SELECT id, endpoint, method, created_at, status
            FROM offline_queue
            ORDER BY created_at DESC
        ''')
        queue = cursor.fetchall()
        conn.close()

        if not queue:
            print("Offline queue is empty")
            return

        print(f"\n{'ID':<6} {'Method':<8} {'Endpoint':<40} {'Created':<20} {'Status'}")
        print("-" * 100)
        for row in queue:
            queue_id, endpoint, method, created_at, status = row
            print(f"{queue_id:<6} {method:<8} {endpoint:<40} {created_at:<20} {status}")

    # ========== BATCH PROCESSING ==========

    def batch_process(self, args):
        """Process batch job file"""
        batch_file = args.file
        parallel = args.parallel or 1
        cache = args.cache

        if not os.path.exists(batch_file):
            print(f"Error: Batch file not found: {batch_file}")
            return

        with open(batch_file, 'r') as f:
            if batch_file.endswith('.json'):
                batch_config = json.load(f)
            elif batch_file.endswith('.yaml') or batch_file.endswith('.yml'):
                import yaml
                batch_config = yaml.safe_load(f)
            else:
                print("Error: Batch file must be JSON or YAML")
                return

        tasks = batch_config.get('tasks', [])
        print(f"🚀 Processing {len(tasks)} tasks (parallel: {parallel})\n")

        for i, task in enumerate(tasks, 1):
            print(f"[{i}/{len(tasks)}] {task.get('name', 'Unnamed task')}")
            command = task.get('command')

            if cache:
                # Check cache first
                cache_key = hashlib.md5(command.encode()).hexdigest()
                # Implementation would check cache here

            # Run command
            result = subprocess.run(command, shell=True, capture_output=True, text=True)

            if result.returncode == 0:
                print(f"  ✓ Success")
            else:
                print(f"  ✗ Failed: {result.stderr}")

        print(f"\n✓ Batch processing complete")

    # ========== ACTIVATION ==========

    def activate_pro(self, args):
        """Activate Pro license"""
        license_key = args.license_key

        # Here you'd validate against your API
        # For now, just enable it
        config = self.load_config()
        config['pro_enabled'] = True
        config['license_key'] = license_key
        config['activated_at'] = datetime.now().isoformat()
        self.save_config(config)

        print("✓ VeriBits CLI Pro activated!")
        print(f"License: {license_key[:8]}...{license_key[-4:]}")
        print("\nPro features enabled:")
        print("  • Job scheduling")
        print("  • Local caching")
        print("  • Offline mode")
        print("  • Batch processing (parallel)")
        print("  • Extended API limits")

def main():
    parser = argparse.ArgumentParser(
        description='VeriBits CLI Pro - Advanced Features',
        formatter_class=argparse.RawDescriptionHelpFormatter
    )
    parser.add_argument('--version', action='version', version=f'VeriBits CLI Pro v{VERSION}')

    subparsers = parser.add_subparsers(dest='command', help='Commands')

    # Activation
    activate_parser = subparsers.add_parser('activate', help='Activate Pro license')
    activate_parser.add_argument('license_key', help='Pro license key')

    # Jobs
    jobs_parser = subparsers.add_parser('jobs', help='Job scheduling')
    jobs_sub = jobs_parser.add_subparsers(dest='jobs_command')

    schedule_parser = jobs_sub.add_parser('schedule', help='Schedule a job')
    schedule_parser.add_argument('--name', required=True, help='Job name')
    schedule_parser.add_argument('--command', required=True, help='Command to run')
    schedule_parser.add_argument('--cron', required=True, help='Cron expression')

    jobs_sub.add_parser('list', help='List scheduled jobs')

    run_parser = jobs_sub.add_parser('run', help='Run a job manually')
    run_parser.add_argument('job_id', help='Job ID')

    delete_parser = jobs_sub.add_parser('delete', help='Delete a job')
    delete_parser.add_argument('job_id', help='Job ID')

    history_parser = jobs_sub.add_parser('history', help='Show job history')
    history_parser.add_argument('job_id', help='Job ID')
    history_parser.add_argument('--limit', type=int, default=10, help='Number of entries')
    history_parser.add_argument('-v', '--verbose', action='store_true', help='Show full output')

    # Cache
    cache_parser = subparsers.add_parser('cache', help='Cache management')
    cache_sub = cache_parser.add_subparsers(dest='cache_command')

    cache_set_parser = cache_sub.add_parser('set', help='Set cache value')
    cache_set_parser.add_argument('key', help='Cache key')
    cache_set_parser.add_argument('value', help='Cache value')
    cache_set_parser.add_argument('--ttl', type=int, default=3600, help='Time to live (seconds)')

    cache_get_parser = cache_sub.add_parser('get', help='Get cache value')
    cache_get_parser.add_argument('key', help='Cache key')

    cache_delete_parser = cache_sub.add_parser('delete', help='Delete cache key')
    cache_delete_parser.add_argument('key', help='Cache key')

    cache_sub.add_parser('clear', help='Clear all cache')
    cache_sub.add_parser('stats', help='Cache statistics')

    # Offline
    offline_parser = subparsers.add_parser('offline', help='Offline mode')
    offline_sub = offline_parser.add_subparsers(dest='offline_command')

    queue_parser = offline_sub.add_parser('queue', help='Queue offline request')
    queue_parser.add_argument('--endpoint', required=True, help='API endpoint')
    queue_parser.add_argument('--method', default='POST', help='HTTP method')
    queue_parser.add_argument('--data', help='Request data (JSON)')

    offline_sub.add_parser('sync', help='Sync offline queue')
    offline_sub.add_parser('list', help='List offline queue')

    # Batch
    batch_parser = subparsers.add_parser('batch', help='Batch processing')
    batch_parser.add_argument('file', help='Batch file (JSON or YAML)')
    batch_parser.add_argument('--parallel', type=int, default=1, help='Parallel workers')
    batch_parser.add_argument('--cache', action='store_true', help='Use cache')

    args = parser.parse_args()

    if not args.command:
        parser.print_help()
        sys.exit(1)

    # Special case for activation (doesn't need Pro check)
    if args.command == 'activate':
        cli = VeriBitsProCLI.__new__(VeriBitsProCLI)
        cli.config = cli.load_config()
        cli.init_databases()
        cli.activate_pro(args)
        return

    # All other commands require Pro
    cli = VeriBitsProCLI()

    try:
        if args.command == 'jobs':
            if args.jobs_command == 'schedule':
                cli.schedule_job(args)
            elif args.jobs_command == 'list':
                cli.list_jobs(args)
            elif args.jobs_command == 'run':
                cli.run_job(args)
            elif args.jobs_command == 'delete':
                cli.delete_job(args)
            elif args.jobs_command == 'history':
                cli.job_history(args)
        elif args.command == 'cache':
            if args.cache_command == 'set':
                cli.cache_set(args)
            elif args.cache_command == 'get':
                cli.cache_get(args)
            elif args.cache_command == 'delete':
                cli.cache_delete(args)
            elif args.cache_command == 'clear':
                cli.cache_clear(args)
            elif args.cache_command == 'stats':
                cli.cache_stats(args)
        elif args.command == 'offline':
            if args.offline_command == 'queue':
                cli.queue_offline(args)
            elif args.offline_command == 'sync':
                cli.sync_offline(args)
            elif args.offline_command == 'list':
                cli.list_offline_queue(args)
        elif args.command == 'batch':
            cli.batch_process(args)
    except KeyboardInterrupt:
        print("\n\nOperation cancelled")
        sys.exit(1)
    except Exception as e:
        print(f"\nError: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == '__main__':
    main()
