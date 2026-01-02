#!/usr/bin/env python3
"""
Multi-threaded cross-platform file hashing system
Generates SHA512 hashes for all files on the system
"""

import os
import sys
import json
import hashlib
import socket
import platform
import threading
from pathlib import Path
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor, as_completed
from collections import defaultdict
from typing import Dict, List, Optional
import urllib.request
import urllib.error


def load_config(config_path: str = 'config.json') -> Optional[Dict]:
    """
    Load configuration from JSON file

    Args:
        config_path: Path to config file

    Returns:
        Config dict or None if not found
    """
    try:
        if os.path.exists(config_path):
            with open(config_path, 'r') as f:
                return json.load(f)
    except Exception as e:
        print(f"Warning: Failed to load config from {config_path}: {e}", file=sys.stderr)
    return None


def upload_results(results: Dict, config: Dict) -> bool:
    """
    Upload scan results to API endpoint

    Args:
        results: Scan results dictionary
        config: Config dict with endpoint_url, email, and api_key

    Returns:
        True if successful, False otherwise
    """
    try:
        endpoint = config.get('endpoint_url')
        if not endpoint:
            print("Error: endpoint_url not found in config", file=sys.stderr)
            return False

        # Prepare request
        data = json.dumps(results).encode('utf-8')
        headers = {
            'Content-Type': 'application/json',
            'User-Agent': 'FileHasher/1.0',
            'X-API-Key': config.get('api_key', ''),
            'X-User-Email': config.get('email', '')
        }

        print(f"Uploading results to {endpoint}...", file=sys.stderr)

        req = urllib.request.Request(endpoint, data=data, headers=headers, method='POST')

        with urllib.request.urlopen(req, timeout=30) as response:
            status_code = response.getcode()
            response_data = response.read().decode('utf-8')

            if status_code == 200 or status_code == 201:
                print(f"Upload successful! Status: {status_code}", file=sys.stderr)
                if response_data:
                    print(f"Response: {response_data}", file=sys.stderr)
                return True
            else:
                print(f"Upload failed with status {status_code}: {response_data}", file=sys.stderr)
                return False

    except urllib.error.HTTPError as e:
        print(f"HTTP Error {e.code}: {e.reason}", file=sys.stderr)
        try:
            error_body = e.read().decode('utf-8')
            print(f"Error details: {error_body}", file=sys.stderr)
        except:
            pass
        return False
    except Exception as e:
        print(f"Upload failed: {e}", file=sys.stderr)
        return False


class FileHasher:
    """High-performance multi-threaded file hasher"""

    # Directories to skip (OS-specific)
    SKIP_DIRS = {
        'linux': {'/proc', '/sys', '/dev', '/run', '/tmp'},
        'darwin': {'/dev', '/private/tmp', '/private/var/tmp', '/Volumes'},
        'windows': {'C:\\$Recycle.Bin', 'C:\\Windows\\Temp', 'C:\\Temp'},
        'freebsd': {'/proc', '/dev', '/tmp'},
        'openbsd': {'/proc', '/dev', '/tmp'},
        'netbsd': {'/proc', '/dev', '/tmp'}
    }

    def __init__(self, root_paths: List[str], num_threads: int = 32, chunk_size: int = 65536, hash_algorithms: List[str] = None):
        """
        Initialize the file hasher

        Args:
            root_paths: List of root directories to scan
            num_threads: Number of worker threads
            chunk_size: File read chunk size in bytes
            hash_algorithms: List of hash algorithms to use (sha256, sha512, or both)
        """
        self.root_paths = root_paths
        self.num_threads = num_threads
        self.chunk_size = chunk_size
        self.hash_algorithms = hash_algorithms or ['sha512']
        self.results = defaultdict(lambda: {'files': []})
        self.lock = threading.Lock()
        self.file_count = 0
        self.error_count = 0
        self.os_type = self._detect_os()
        self.skip_dirs = self._get_skip_dirs()

    def _detect_os(self) -> str:
        """Detect operating system"""
        system = platform.system().lower()
        if system == 'linux':
            return 'linux'
        elif system == 'darwin':
            return 'darwin'
        elif system == 'windows':
            return 'windows'
        elif 'bsd' in system:
            return system
        return 'unknown'

    def _get_skip_dirs(self) -> set:
        """Get directories to skip based on OS"""
        return self.SKIP_DIRS.get(self.os_type, set())

    def _should_skip_dir(self, dir_path: str) -> bool:
        """Check if directory should be skipped"""
        for skip_dir in self.skip_dirs:
            if dir_path.startswith(skip_dir):
                return True
        return False

    def _get_hostname(self) -> str:
        """Get system hostname"""
        try:
            return socket.gethostname()
        except Exception:
            return "unknown"

    def _get_local_ip(self) -> str:
        """Get local IP address"""
        try:
            # Create a socket to determine local IP
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.connect(("8.8.8.8", 80))
            ip = s.getsockname()[0]
            s.close()
            return ip
        except Exception:
            return "127.0.0.1"

    def _get_public_ip(self) -> str:
        """Get public IP address"""
        try:
            # Try multiple services for reliability
            services = [
                'https://api.ipify.org',
                'https://icanhazip.com',
                'https://ifconfig.me/ip'
            ]

            for service in services:
                try:
                    req = urllib.request.Request(service, headers={'User-Agent': 'Mozilla/5.0'})
                    with urllib.request.urlopen(req, timeout=5) as response:
                        return response.read().decode('utf-8').strip()
                except Exception:
                    continue

            return "unknown"
        except Exception:
            return "unknown"

    def _hash_file(self, file_path: str) -> Optional[Dict[str, str]]:
        """
        Compute hash(es) of a file using specified algorithms

        Args:
            file_path: Path to file

        Returns:
            Dict mapping algorithm name to hex digest, or None on error
        """
        try:
            # Initialize hash objects for all requested algorithms
            hashers = {}
            for algo in self.hash_algorithms:
                if algo == 'sha256':
                    hashers['sha256'] = hashlib.sha256()
                elif algo == 'sha512':
                    hashers['sha512'] = hashlib.sha512()
                else:
                    raise ValueError(f"Unsupported hash algorithm: {algo}")

            # Read file once and update all hashers
            with open(file_path, 'rb') as f:
                while chunk := f.read(self.chunk_size):
                    for hasher in hashers.values():
                        hasher.update(chunk)

            # Return hex digests
            return {algo: hasher.hexdigest() for algo, hasher in hashers.items()}
        except (PermissionError, OSError, IOError) as e:
            # Silently skip files we can't read
            return None

    def _process_file(self, file_path: Path, dir_name: str) -> Optional[Dict]:
        """
        Process a single file

        Args:
            file_path: Path object for file
            dir_name: Parent directory name

        Returns:
            Dict with file info or None
        """
        try:
            # Skip symlinks to avoid loops
            if file_path.is_symlink():
                return None

            # Only process regular files
            if not file_path.is_file():
                return None

            file_hashes = self._hash_file(str(file_path))
            if file_hashes is None:
                with self.lock:
                    self.error_count += 1
                return None

            with self.lock:
                self.file_count += 1
                if self.file_count % 1000 == 0:
                    print(f"Processed {self.file_count} files...", file=sys.stderr)

            # Build result with all hash algorithms
            result = {'file_name': str(file_path)}

            # Add hashes with appropriate keys
            if len(self.hash_algorithms) == 1:
                # Single algorithm - use 'file_hash' for backward compatibility
                result['file_hash'] = file_hashes[self.hash_algorithms[0]]
            else:
                # Multiple algorithms - use separate keys
                for algo, hash_value in file_hashes.items():
                    result[f'file_hash_{algo}'] = hash_value

            return result

        except Exception as e:
            return None

    def _walk_directory(self, root_path: str):
        """
        Walk directory tree and yield all files

        Args:
            root_path: Root directory to scan
        """
        try:
            for dirpath, dirnames, filenames in os.walk(root_path, topdown=True):
                # Skip directories we should avoid
                if self._should_skip_dir(dirpath):
                    dirnames.clear()  # Don't recurse into subdirectories
                    continue

                # Remove directories we should skip from the walk
                dirnames[:] = [d for d in dirnames if not self._should_skip_dir(os.path.join(dirpath, d))]

                for filename in filenames:
                    file_path = Path(dirpath) / filename
                    yield file_path, dirpath

        except (PermissionError, OSError) as e:
            # Skip directories we can't access
            pass

    def scan(self) -> Dict:
        """
        Scan all files and generate hash mapping

        Returns:
            Dictionary with system info and file hashes
        """
        print(f"Starting scan with {self.num_threads} threads...", file=sys.stderr)
        print(f"OS: {self.os_type}", file=sys.stderr)
        print(f"Hash algorithms: {', '.join(self.hash_algorithms)}", file=sys.stderr)
        print(f"Root paths: {self.root_paths}", file=sys.stderr)

        # Collect all files first
        files_to_process = []
        print("Building file list...", file=sys.stderr)

        for root_path in self.root_paths:
            for file_path, dirpath in self._walk_directory(root_path):
                files_to_process.append((file_path, dirpath))

        print(f"Found {len(files_to_process)} files to process", file=sys.stderr)

        # Process files in parallel
        with ThreadPoolExecutor(max_workers=self.num_threads) as executor:
            futures = []
            for file_path, dirpath in files_to_process:
                future = executor.submit(self._process_file, file_path, dirpath)
                futures.append((future, dirpath))

            # Collect results
            for future, dirpath in futures:
                try:
                    result = future.result()
                    if result:
                        with self.lock:
                            self.results[dirpath]['files'].append(result)
                except Exception as e:
                    with self.lock:
                        self.error_count += 1

        print(f"\nScan complete!", file=sys.stderr)
        print(f"Files processed: {self.file_count}", file=sys.stderr)
        print(f"Errors encountered: {self.error_count}", file=sys.stderr)

        # Build final output structure
        directories = []
        for dir_name, data in self.results.items():
            if data['files']:  # Only include directories with files
                directories.append({
                    'dir_name': dir_name,
                    'files': data['files']
                })

        output = {
            'system_name': self._get_hostname(),
            'system_ip': self._get_local_ip(),
            'system_public': self._get_public_ip(),
            'os_type': self.os_type,
            'os_version': platform.platform(),
            'hash_algorithms': self.hash_algorithms,
            'scan_date': datetime.utcnow().isoformat() + 'Z',
            'total_files': self.file_count,
            'total_errors': self.error_count,
            'directories': directories
        }

        return output


def get_default_roots() -> List[str]:
    """Get default root paths based on OS"""
    system = platform.system().lower()

    if system == 'windows':
        # Scan all drives on Windows
        import string
        drives = []
        for letter in string.ascii_uppercase:
            drive = f"{letter}:\\"
            if os.path.exists(drive):
                drives.append(drive)
        return drives if drives else ['C:\\']
    else:
        # Unix-like systems
        return ['/']


def main():
    """Main entry point"""
    import argparse

    parser = argparse.ArgumentParser(
        description='Multi-threaded file hasher for system inventory'
    )
    parser.add_argument(
        '--root',
        nargs='+',
        default=None,
        help='Root directories to scan (default: system roots)'
    )
    parser.add_argument(
        '--files',
        nargs='+',
        default=None,
        help='Specific file(s) to hash instead of scanning directories'
    )
    parser.add_argument(
        '--threads',
        type=int,
        default=32,
        help='Number of worker threads (default: 32)'
    )
    parser.add_argument(
        '--output',
        type=str,
        default='file_hashes.json',
        help='Output JSON file (default: file_hashes.json)'
    )
    parser.add_argument(
        '--chunk-size',
        type=int,
        default=65536,
        help='File read chunk size in bytes (default: 65536)'
    )
    parser.add_argument(
        '--hash',
        nargs='+',
        choices=['sha256', 'sha512'],
        default=['sha512'],
        help='Hash algorithm(s) to use (default: sha512). Can specify multiple: --hash sha256 sha512'
    )
    parser.add_argument(
        '--config',
        type=str,
        default='config.json',
        help='Path to config file (default: config.json)'
    )
    parser.add_argument(
        '--no-upload',
        action='store_true',
        help='Disable upload even if config exists'
    )

    args = parser.parse_args()

    # Handle file-specific hashing
    if args.files:
        # Validate that all provided files exist
        valid_files = []
        for file_path in args.files:
            if os.path.isfile(file_path):
                valid_files.append(file_path)
            else:
                print(f"Warning: '{file_path}' is not a valid file, skipping", file=sys.stderr)

        if not valid_files:
            print("Error: No valid files provided", file=sys.stderr)
            sys.exit(1)

        print(f"Hashing {len(valid_files)} file(s)...", file=sys.stderr)
        print(f"Hash algorithms: {', '.join(args.hash)}", file=sys.stderr)

        # Create a minimal hasher instance just for hashing
        hasher = FileHasher(
            root_paths=[],
            num_threads=1,
            chunk_size=args.chunk_size,
            hash_algorithms=args.hash
        )

        # Hash each file
        file_results = []
        for file_path in valid_files:
            abs_path = os.path.abspath(file_path)
            path_obj = Path(abs_path)
            result = hasher._process_file(path_obj, os.path.dirname(abs_path))
            if result:
                file_results.append(result)
                print(f"Hashed: {file_path}", file=sys.stderr)
            else:
                print(f"Failed to hash: {file_path}", file=sys.stderr)

        # Build results in the same format as directory scan
        results = {
            'system_name': hasher._get_hostname(),
            'system_ip': hasher._get_local_ip(),
            'system_public': hasher._get_public_ip(),
            'os_type': hasher.os_type,
            'os_version': platform.platform(),
            'hash_algorithms': args.hash,
            'scan_date': datetime.utcnow().isoformat() + 'Z',
            'total_files': len(file_results),
            'total_errors': len(valid_files) - len(file_results),
            'directories': [{'dir_name': 'specified_files', 'files': file_results}] if file_results else []
        }
    else:
        # Get root paths
        root_paths = args.root if args.root else get_default_roots()

        # Verify root paths exist
        valid_roots = [r for r in root_paths if os.path.exists(r)]
        if not valid_roots:
            print("Error: No valid root paths found", file=sys.stderr)
            sys.exit(1)

        # Create hasher and scan
        hasher = FileHasher(
            root_paths=valid_roots,
            num_threads=args.threads,
            chunk_size=args.chunk_size,
            hash_algorithms=args.hash
        )

        results = hasher.scan()

    # Write output to local file
    print(f"\nWriting results to {args.output}...", file=sys.stderr)
    with open(args.output, 'w') as f:
        json.dump(results, f, indent=2)

    print(f"Output written to {args.output}", file=sys.stderr)
    print(f"File size: {os.path.getsize(args.output)} bytes", file=sys.stderr)

    # Upload results if config exists and upload is not disabled
    if not args.no_upload:
        config = load_config(args.config)
        if config:
            print(f"\nConfig found at {args.config}", file=sys.stderr)
            if upload_results(results, config):
                print("✓ Results uploaded successfully", file=sys.stderr)
            else:
                print("✗ Upload failed - results saved locally only", file=sys.stderr)
        else:
            print(f"\nNo config found at {args.config} - skipping upload", file=sys.stderr)
    else:
        print("\nUpload disabled - results saved locally only", file=sys.stderr)

    print("\nComplete!", file=sys.stderr)


if __name__ == '__main__':
    # On Unix systems, check if we should use setuid behavior
    if platform.system() != 'Windows':
        # Set process to only read files (drop write permissions where possible)
        try:
            import resource
            # Set resource limits if running as root
            if os.geteuid() == 0:
                print("Warning: Running as root. Consider running as unprivileged user.", file=sys.stderr)
        except ImportError:
            pass

    main()
