# The Sleuth Kit (TSK) Integration - Design Document

## 🔍 Overview

The Sleuth Kit is a collection of command-line digital forensics tools that can:
- Analyze disk images (raw, E01, AFF, etc.)
- List files and directories from disk images
- Recover deleted files
- Extract specific files
- Generate forensic timelines
- Analyze file system metadata
- Examine partitions

## 🎯 Integration Strategy

### Three-Tier Approach

```
┌─────────────────────────────────────────────┐
│  1. Web Tool (Upload & Analyze)             │
│     - Small disk images (< 2GB)             │
│     - Quick analysis                        │
│     - Browser-based                         │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  2. System Client (Local Analysis)          │
│     - Large disk images (any size)          │
│     - Full TSK suite                        │
│     - Send results to VeriBits              │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│  3. Server Processing (Hybrid)              │
│     - Process uploaded images               │
│     - Receive client results                │
│     - Store and display findings            │
└─────────────────────────────────────────────┘
```

## 🛠️ TSK Commands to Integrate

### File Listing & Extraction
```bash
fls -r -p image.dd           # List all files recursively
icat image.dd 128            # Extract file by inode
tsk_recover image.dd output/ # Recover all files
```

### File System Analysis
```bash
fsstat image.dd              # File system statistics
ils -e image.dd              # List deleted inodes
mmls image.dd                # Display partition layout
```

### Timeline Generation
```bash
fls -m / -r image.dd > body.txt
mactime -b body.txt > timeline.csv
```

### Metadata Analysis
```bash
istat image.dd 128           # Inode statistics
ffind image.dd 128           # Find file name from inode
```

## 📦 Architecture

### 1. Web Tool Component

**File:** `app/public/tool/disk-forensics.php`

**Features:**
- Upload disk image (max 2GB)
- Select analysis type
- Display results in web UI
- Download extracted files
- View timelines
- Search within image

**Supported Operations:**
- List files
- Extract specific file
- Recover deleted files
- View file system info
- Generate timeline

### 2. System Client Component

**File:** `veribits-system-client/disk_analyzer.py`

**Features:**
- Analyze local disk images (any size)
- Run full TSK suite locally
- Stream results to VeriBits API
- Support large images (100GB+)
- Offline mode with export

**Commands Supported:**
```bash
# List all files
veribits disk analyze image.dd --list-files

# Recover deleted files
veribits disk recover image.dd --output recovered/

# Generate timeline
veribits disk timeline image.dd --output timeline.csv

# Extract specific file
veribits disk extract image.dd --inode 128 --output file.dat

# Full analysis
veribits disk scan image.dd --all
```

### 3. Server-Side Controller

**File:** `app/src/Controllers/DiskForensicsController.php`

**Endpoints:**
```
POST /api/v1/forensics/disk/upload       - Upload disk image
POST /api/v1/forensics/disk/analyze      - Analyze image
POST /api/v1/forensics/disk/list-files   - List files in image
POST /api/v1/forensics/disk/extract      - Extract specific file
POST /api/v1/forensics/disk/recover      - Recover deleted files
POST /api/v1/forensics/disk/timeline     - Generate timeline
POST /api/v1/forensics/disk/fsstat       - File system stats
GET  /api/v1/forensics/disk/results/:id  - Get analysis results
```

## 🔐 Security Considerations

### Upload Limits
- Max file size: 2GB for web upload
- Unlimited for system client
- Chunked upload support
- Resume capability

### Sandboxing
- Run TSK in isolated environment
- Limit CPU/memory usage
- Timeout for long operations
- Auto-cleanup after analysis

### Access Control
- Require authentication
- Pro/Enterprise tier only
- Rate limiting
- Audit logging

## 💻 Implementation Details

### Supported Image Formats

| Format | Extension | Description | Web Upload | Client |
|--------|-----------|-------------|------------|--------|
| Raw DD | .dd, .raw, .img | Raw disk image | ✅ | ✅ |
| E01 | .E01 | Expert Witness Format | ✅ | ✅ |
| AFF | .aff | Advanced Forensic Format | ✅ | ✅ |
| Split Raw | .001, .002 | Split images | ❌ | ✅ |
| VHD/VHDX | .vhd, .vhdx | Virtual Hard Disk | ✅ | ✅ |
| VMDK | .vmdk | VMware disk | ✅ | ✅ |

### Supported File Systems

- **Windows**: NTFS, FAT12/16/32, exFAT
- **Linux**: Ext2/3/4, XFS, BtrFS
- **macOS**: HFS+, APFS (limited)
- **Other**: ISO9660, UFS, YAFFS2

### Performance Expectations

| Operation | 100MB Image | 1GB Image | 10GB Image |
|-----------|-------------|-----------|------------|
| List Files | 2-5 sec | 10-30 sec | 1-3 min |
| Recover Deleted | 5-10 sec | 30-60 sec | 5-10 min |
| Timeline Gen | 10-20 sec | 1-2 min | 10-20 min |
| Full Scan | 30 sec | 5 min | 30+ min |

## 📊 Features Matrix

### Web Tool Features

```
┌─────────────────────────────────────────────┐
│  Disk Forensics Tool                        │
├─────────────────────────────────────────────┤
│                                             │
│  📤 Upload Image                            │
│  └─ Drag & drop or file select             │
│  └─ Max 2GB, .dd/.E01/.aff                 │
│                                             │
│  🔍 Analysis Options                        │
│  ☑ List all files                          │
│  ☑ Recover deleted files                   │
│  ☑ Generate timeline                       │
│  ☑ File system statistics                  │
│  ☑ Partition layout                        │
│                                             │
│  📊 Results Display                         │
│  └─ File tree view                         │
│  └─ Timeline visualization                 │
│  └─ Download extracted files               │
│  └─ Export results (JSON/CSV)              │
└─────────────────────────────────────────────┘
```

### System Client Features

```
┌─────────────────────────────────────────────┐
│  VeriBits Disk Analyzer                     │
├─────────────────────────────────────────────┤
│                                             │
│  $ veribits disk analyze image.dd          │
│                                             │
│  Analyzing: image.dd (15.2 GB)             │
│  File System: NTFS                         │
│  Partitions: 3                             │
│                                             │
│  [████████████████░░░░] 80% Complete       │
│                                             │
│  Files found: 45,238                       │
│  Deleted files: 1,523                      │
│  Timeline entries: 128,445                 │
│                                             │
│  Options:                                  │
│  --list-files      List all files          │
│  --recover         Recover deleted files   │
│  --timeline        Generate timeline       │
│  --extract INODE   Extract specific file   │
│  --upload          Upload to VeriBits      │
└─────────────────────────────────────────────┘
```

## 🔧 Installation Requirements

### Server-Side Dependencies

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install -y sleuthkit
```

**macOS:**
```bash
brew install sleuthkit
```

**Verify Installation:**
```bash
fls -V
icat -V
tsk_recover -V
```

### Client-Side Dependencies

**Python Client:**
```bash
pip install pytsk3
pip install dfvfs  # For E01/AFF support
```

## 📝 Usage Examples

### Web Tool Usage

1. **Upload Image**
```
Navigate to: https://veribits.com/tool/disk-forensics.php
Click "Upload Image" or drag & drop
Select analysis type
Wait for results
```

2. **View Results**
```
- File tree with all files
- Timeline visualization
- Download buttons for recovered files
- Export results as JSON/CSV
```

### System Client Usage

**List Files:**
```bash
veribits disk analyze /path/to/image.dd --list-files
```

**Recover Deleted Files:**
```bash
veribits disk recover /path/to/image.dd --output ./recovered/
```

**Generate Timeline:**
```bash
veribits disk timeline /path/to/image.dd --output timeline.csv
```

**Full Analysis with Upload:**
```bash
veribits disk scan /path/to/image.dd --all --upload
```

### API Usage

**Upload Image:**
```bash
curl -X POST https://veribits.com/api/v1/forensics/disk/upload \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -F "file=@image.dd" \
  -F "name=evidence_001"
```

**Analyze Image:**
```bash
curl -X POST https://veribits.com/api/v1/forensics/disk/analyze \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "image_id": "uuid-here",
    "operations": ["list_files", "recover_deleted", "timeline"]
  }'
```

**Get Results:**
```bash
curl https://veribits.com/api/v1/forensics/disk/results/uuid-here \
  -H "Authorization: Bearer YOUR_API_KEY"
```

## 🎨 UI/UX Design

### File Tree Display
```
📁 / (Root)
├── 📁 Windows
│   ├── 📁 System32
│   │   ├── 📄 notepad.exe (59 KB)
│   │   └── 📄 cmd.exe (273 KB)
│   └── 📁 Users
│       └── 📁 John
│           └── 📄 document.docx (34 KB) [DELETED]
├── 📁 Program Files
└── 📁 Users
```

### Timeline View
```
┌────────────┬──────────────────────┬─────────────┐
│ Timestamp  │ File                 │ Action      │
├────────────┼──────────────────────┼─────────────┤
│ 2024-01-15 │ /Users/John/doc.docx │ Created     │
│ 2024-01-15 │ /Users/John/doc.docx │ Modified    │
│ 2024-01-16 │ /Users/John/doc.docx │ Accessed    │
│ 2024-01-17 │ /Users/John/doc.docx │ Deleted     │
└────────────┴──────────────────────┴─────────────┘
```

## 💰 Pricing Tiers

### Free Tier
- 3 analyses per month
- Max 100MB images
- Basic operations only
- Results expire in 7 days

### Pro Tier ($29.99/mo)
- 50 analyses per month
- Max 2GB images
- All operations
- Results stored 30 days
- Priority processing

### Enterprise Tier ($149.99/mo)
- Unlimited analyses
- Unlimited image size (client)
- All operations
- Results stored indefinitely
- Dedicated support
- API access
- Batch processing

## 🚀 Implementation Phases

### Phase 1: Server-Side Core (2-3 days)
- ✅ Install TSK on server
- ✅ Create DiskForensicsController
- ✅ Implement basic operations (fls, icat, fsstat)
- ✅ API endpoints for upload/analyze
- ✅ File storage and cleanup

### Phase 2: Web Tool (1-2 days)
- ✅ Create disk-forensics.php frontend
- ✅ Upload interface with drag & drop
- ✅ Results display (file tree, timeline)
- ✅ Download functionality
- ✅ Export results

### Phase 3: System Client (2-3 days)
- ✅ Create disk_analyzer.py
- ✅ Implement TSK wrapper
- ✅ Upload results to API
- ✅ Progress indicators
- ✅ CLI interface

### Phase 4: Advanced Features (ongoing)
- ⏳ Large file chunked upload
- ⏳ Real-time streaming analysis
- ⏳ Advanced timeline visualization
- ⏳ Hash database integration
- ⏳ YARA rule scanning

## 📚 Documentation

### User Guide Topics
1. Getting Started with Disk Forensics
2. Uploading Disk Images
3. Understanding Analysis Results
4. Using the System Client
5. Recovering Deleted Files
6. Timeline Analysis
7. Best Practices

### API Documentation
- Complete endpoint reference
- Authentication
- Request/response examples
- Error codes
- Rate limits

## 🔍 Use Cases

### Law Enforcement
- Analyze seized hard drives
- Recover deleted evidence
- Generate forensic timelines
- Court-admissible reports

### Corporate Security
- Incident response
- Data breach analysis
- Employee computer forensics
- Insider threat investigation

### Data Recovery
- Recover accidentally deleted files
- Extract data from corrupted drives
- Retrieve specific files
- Backup verification

### Security Research
- Malware analysis (disk artifacts)
- Rootkit detection
- File system research
- Forensic tool testing

## ⚖️ Legal & Compliance

### Legal Notice
"This tool is for authorized forensic analysis only. Only analyze disk images you own or have legal authorization to examine. Unauthorized access to computer systems is illegal."

### Evidence Preservation
- Chain of custody tracking
- Hash verification (MD5/SHA256)
- Audit logging
- Timestamping
- Report generation

### Compliance
- NIST forensic guidelines
- ISO/IEC 27037 compliance
- ACPO principles
- Court admissibility standards

## 📊 Success Metrics

### Technical Metrics
- Upload success rate: >99%
- Analysis completion rate: >95%
- Average processing time: <5min for 1GB
- Deleted file recovery rate: 60-80%

### Business Metrics
- Tool adoption rate
- API usage growth
- Conversion to paid tiers
- User satisfaction score

## 🎯 Competitive Advantage

### vs. Autopsy (TSK GUI)
- ✅ Web-based (no installation)
- ✅ API access
- ✅ Cloud processing
- ✅ Easier for beginners

### vs. FTK/EnCase
- ✅ Free/affordable pricing
- ✅ Open source foundation
- ✅ Modern web interface
- ✅ CLI automation

### vs. Other Cloud Forensics
- ✅ Part of full security suite
- ✅ System client for large images
- ✅ Full TSK capabilities
- ✅ Developer-friendly API

---

**Ready to Implement!** This design provides comprehensive TSK integration for VeriBits with web tool, system client, and full API support.
