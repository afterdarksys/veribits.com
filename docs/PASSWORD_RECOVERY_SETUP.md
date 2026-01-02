# Password Recovery Tool - Installation & Setup

## Overview

The Password Recovery Tool allows users to analyze, remove passwords, and attempt password cracking for:
- PDF files
- Office documents (DOCX, XLSX, PPTX)
- ZIP archives (non-AES)

## Required Dependencies

### System Packages

#### Ubuntu/Debian
```bash
sudo apt-get update
sudo apt-get install -y \
    python3 \
    python3-pip \
    qpdf \
    poppler-utils
```

#### macOS (Homebrew)
```bash
brew install python3 qpdf poppler
```

#### Docker (Add to Dockerfile)
```dockerfile
RUN apk add --no-cache \
    python3 \
    py3-pip \
    qpdf \
    poppler-utils
```

### Python Packages

Install required Python libraries:

```bash
pip3 install pikepdf msoffcrypto-tool
```

Or add to `requirements.txt`:
```
pikepdf>=8.0.0
msoffcrypto-tool>=5.0.0
```

## Installation Steps

### 1. Install System Dependencies

**On Production Server (Amazon Linux 2/Ubuntu):**
```bash
# Update package manager
sudo yum update -y  # or sudo apt-get update

# Install Python 3
sudo yum install -y python3 python3-pip  # or sudo apt-get install python3 python3-pip

# Install qpdf
sudo yum install -y qpdf  # or sudo apt-get install qpdf

# Install poppler (for pdfinfo)
sudo yum install -y poppler-utils  # or sudo apt-get install poppler-utils
```

### 2. Install Python Packages

```bash
sudo pip3 install pikepdf msoffcrypto-tool
```

### 3. Verify Installation

```bash
# Test qpdf
qpdf --version

# Test pdfinfo
pdfinfo -v

# Test Python modules
python3 -c "import pikepdf; print('pikepdf OK')"
python3 -c "import msoffcrypto; print('msoffcrypto OK')"
```

### 4. Test the API

```bash
# Create a test password-protected PDF
echo "test" | qpdf --empty --encrypt user owner 256 -- test_protected.pdf

# Test password removal
curl -X POST http://localhost/api/v1/tools/password-recovery/remove \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@test_protected.pdf" \
  -F "password=user" \
  --output unlocked.pdf

# Verify unlocked file
ls -lh unlocked.pdf
```

## Docker Setup

Add to your `docker/Dockerfile`:

```dockerfile
# Install password recovery dependencies
RUN apk add --no-cache \
    python3 \
    py3-pip \
    qpdf \
    poppler-utils && \
    pip3 install --no-cache-dir \
        pikepdf>=8.0.0 \
        msoffcrypto-tool>=5.0.0
```

Rebuild your Docker image:
```bash
docker build -t veribits:latest -f docker/Dockerfile .
```

## API Endpoints

### 1. Analyze File
```bash
POST /api/v1/tools/password-recovery/analyze
Content-Type: multipart/form-data

file: [binary file data]
```

**Response:**
```json
{
  "success": true,
  "data": {
    "filename": "document.pdf",
    "size": 102400,
    "type": "pdf",
    "is_encrypted": true,
    "encryption_type": "AES-256"
  }
}
```

### 2. Remove Password
```bash
POST /api/v1/tools/password-recovery/remove
Content-Type: multipart/form-data

file: [binary file data]
password: [password string]
```

**Response:** Binary file download (unlocked file)

### 3. Crack Password
```bash
POST /api/v1/tools/password-recovery/crack
Content-Type: multipart/form-data

file: [binary file data]
wordlist: common|numeric|alpha
max_attempts: 1000
method: dictionary
```

**Response:**
```json
{
  "success": true,
  "data": {
    "found": true,
    "password": "123456",
    "attempts": 42,
    "time_seconds": 2.5
  }
}
```

## CLI Usage

### Using VeriBits CLI

```bash
# Analyze file
veribits password analyze document.pdf

# Remove password
veribits password remove document.pdf --password "mypassword"

# Crack password
veribits password crack document.pdf --wordlist common --max-attempts 1000
```

## Limitations

### Supported Formats
- ✅ PDF (all encryption types via qpdf/pikepdf)
- ✅ Office 2007+ (DOCX, XLSX, PPTX)
- ✅ ZIP (ZipCrypto only)
- ❌ ZIP with AES-256 encryption
- ❌ Office 97-2003 (DOC, XLS, PPT)
- ❌ RAR archives

### Performance
- **Password Removal**: Near instant (when password is known)
- **Password Cracking**:
  - Common passwords: ~100 attempts/second
  - Numeric passwords: ~50 attempts/second
  - Random passwords: ~20 attempts/second

### Security Considerations
- Files are deleted immediately after processing
- Passwords are not logged or stored
- Maximum file size: 50MB
- Rate limiting applies to anonymous users

## Troubleshooting

### "qpdf command not found"
```bash
# Install qpdf
sudo apt-get install qpdf  # Ubuntu/Debian
sudo yum install qpdf      # Amazon Linux/RHEL
brew install qpdf          # macOS
```

### "ModuleNotFoundError: No module named 'pikepdf'"
```bash
pip3 install pikepdf
```

### "Failed to remove PDF password"
- Verify password is correct
- Check if PDF uses unsupported encryption
- Try updating qpdf: `sudo apt-get upgrade qpdf`

### Docker Container Issues
```bash
# Rebuild with dependencies
docker build --no-cache -t veribits:latest -f docker/Dockerfile .

# Test inside container
docker exec -it <container_id> sh
python3 -c "import pikepdf; print('OK')"
```

## Production Deployment

1. Update your deployment script to install dependencies:

```bash
#!/bin/bash
# In scripts/deploy-to-aws.sh

# Install dependencies on target server
ssh user@server << 'EOF'
  sudo yum install -y python3 python3-pip qpdf poppler-utils
  sudo pip3 install pikepdf msoffcrypto-tool
EOF
```

2. Or use Docker image with pre-installed dependencies

3. Test endpoints after deployment:
```bash
curl https://veribits.com/tool/password-recovery.php
```

## Cost Considerations

- **CPU Usage**: Password cracking is CPU-intensive
- **Memory**: ~50-100MB per concurrent request
- **Recommended**:
  - Limit max_attempts for anonymous users
  - Use rate limiting (already implemented)
  - Consider background job queue for large cracking jobs

## Support

For issues or questions:
- GitHub: https://github.com/afterdarksystems/veribits
- Email: support@afterdarksys.com
