# File Hasher - Cross-Platform System Inventory Tool

High-performance, multi-threaded file hashing system for generating cryptographic hashes of all files on a system. Supports SHA256 and SHA512 algorithms.

## Features

- **Blazing Fast**: Multi-threaded architecture (default 32 threads)
- **Cross-Platform**: Works on Linux, Windows, macOS, and BSD systems
- **Flexible Hashing**: Support for SHA256, SHA512, or both simultaneously
- **Smart Scanning**: Automatically skips system directories and handles permissions gracefully
- **Complete Inventory**: Captures system metadata (hostname, local IP, public IP, OS info)
- **JSON Output**: Structured JSON format for easy integration

## Installation

### Requirements

- Python 3.8 or higher (uses walrus operator `:=`)
- No external dependencies required (uses only standard library)

### Direct Usage

```bash
chmod +x file_hasher.py
./file_hasher.py --help
```

### Compilation with PyInstaller

For creating a standalone executable:

```bash
# Install PyInstaller
pip install pyinstaller

# Compile to single executable
pyinstaller --onefile --name file-hasher file_hasher.py

# The executable will be in dist/file-hasher
```

#### Platform-Specific Compilation

**Linux/BSD:**
```bash
pyinstaller --onefile --name file-hasher file_hasher.py
```

**macOS:**
```bash
pyinstaller --onefile --name file-hasher --target-arch universal2 file_hasher.py
```

**Windows:**
```bash
pyinstaller --onefile --name file-hasher.exe file_hasher.py
```

## Usage

### Basic Usage

Scan entire system with default settings (SHA512, 32 threads):

```bash
# Linux/macOS/BSD (requires sudo for full system access)
sudo ./file_hasher.py

# Windows (run as Administrator)
file_hasher.py
```

### Advanced Usage

**Scan specific directory:**
```bash
./file_hasher.py --root /home/user/documents
```

**Scan multiple directories:**
```bash
./file_hasher.py --root /etc /var/log /home
```

**Use SHA256 (for older systems):**
```bash
./file_hasher.py --hash sha256
```

**Compute both SHA256 and SHA512:**
```bash
./file_hasher.py --hash sha256 sha512
```

**Adjust thread count:**
```bash
./file_hasher.py --threads 64  # More threads for faster processing
./file_hasher.py --threads 8   # Fewer threads for limited systems
```

**Custom output file:**
```bash
./file_hasher.py --output /tmp/system_inventory.json
```

**Performance tuning:**
```bash
./file_hasher.py --threads 64 --chunk-size 131072  # 128KB chunks
```

### API Upload Configuration

The file hasher can automatically upload scan results to a remote API endpoint. Create a `config.json` file with your API credentials:

**Create config.json:**
```bash
cp config.json.example config.json
# Edit config.json with your credentials
```

**config.json format:**
```json
{
  "endpoint_url": "https://api.example.com/hashes/upload",
  "email": "user@example.com",
  "api_key": "your-api-key-here"
}
```

**Usage with upload:**
```bash
# Will automatically upload if config.json exists
./file_hasher.py

# Disable upload even if config exists
./file_hasher.py --no-upload

# Use custom config file location
./file_hasher.py --config /path/to/config.json
```

The tool will:
1. Always save results to local JSON file (default: `file_hashes.json`)
2. If `config.json` exists and `--no-upload` is not set, upload results to the API endpoint
3. Include credentials in HTTP headers: `X-API-Key` and `X-User-Email`
4. Report success/failure of upload while keeping local file as backup

**Note:** The `config.json` file is automatically ignored by git to prevent accidentally committing credentials.

### Complete Example

```bash
sudo ./file_hasher.py \
  --root / \
  --hash sha512 \
  --threads 32 \
  --output /var/log/file_inventory_$(date +%Y%m%d).json
```

## Output Format

```json
{
  "system_name": "hostname",
  "system_ip": "192.168.1.100",
  "system_public": "203.0.113.1",
  "os_type": "linux",
  "os_version": "Linux-5.15.0-generic-x86_64-with-glibc2.35",
  "hash_algorithms": ["sha512"],
  "scan_date": "2024-01-15T10:30:45.123456Z",
  "total_files": 150000,
  "total_errors": 42,
  "directories": [
    {
      "dir_name": "/home/user",
      "files": [
        {
          "file_name": "/home/user/document.txt",
          "file_hash": "abc123..."
        }
      ]
    }
  ]
}
```

When using multiple hash algorithms:
```json
{
  "file_name": "/path/to/file",
  "file_hash_sha256": "def456...",
  "file_hash_sha512": "abc123..."
}
```

## Performance

### Benchmarks

Typical performance on modern hardware:

- **Small files** (< 1MB): ~10,000 files/second
- **Medium files** (1-100MB): ~1,000 files/second
- **Large files** (> 100MB): Limited by disk I/O

### Optimization Tips

1. **More threads**: Increase `--threads` for systems with many small files
2. **Larger chunks**: Increase `--chunk-size` for systems with large files
3. **SSD vs HDD**: Performance on SSD can be 10x faster than HDD
4. **Single hash**: Use one algorithm instead of both for 2x speed

## Security Considerations

### Read-Only Operation

The tool only reads files and never modifies them. On Unix systems, it checks for root execution and warns appropriately.

### Skipped Directories

For safety and performance, the following are automatically skipped:

**Linux:**
- `/proc`, `/sys`, `/dev`, `/run`, `/tmp`

**macOS:**
- `/dev`, `/private/tmp`, `/private/var/tmp`, `/Volumes`

**Windows:**
- `C:\$Recycle.Bin`, `C:\Windows\Temp`, `C:\Temp`

**BSD:**
- `/proc`, `/dev`, `/tmp`

### Permissions

Files that cannot be read due to permissions are silently skipped and counted in `total_errors`. The tool never attempts to modify permissions.

## Cross-Platform Notes

### Linux/BSD
- Run with `sudo` for full system access
- Handles setuid/setgid files safely (read-only)
- Skips special filesystems (`/proc`, `/sys`, etc.)

### macOS
- Run with `sudo` for full system access
- Automatically skips mounted volumes
- Compatible with APFS, HFS+, and other filesystems

### Windows
- Run as Administrator for full system access
- Automatically detects all drives (C:\, D:\, etc.)
- Handles NTFS permissions and alternate data streams

## Troubleshooting

### High Memory Usage
Reduce thread count: `--threads 16`

### Slow Performance
- Increase threads: `--threads 64`
- Increase chunk size: `--chunk-size 131072`
- Use SSD instead of HDD
- Use single hash algorithm

### Permission Errors
- Run with elevated privileges (sudo/Administrator)
- Check `total_errors` in output for count of inaccessible files

### Public IP Shows "unknown"
- Check internet connectivity
- Firewall may be blocking outbound connections
- Not an error - tool continues normally

## License

This tool is designed for security auditing, file integrity monitoring, and system inventory purposes.

## Support

For issues or questions, refer to your system administrator or security team.
