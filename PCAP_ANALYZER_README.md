# AI-Powered PCAP Analyzer for VeriBits

## Overview

A comprehensive, production-ready network packet capture analyzer with DNS troubleshooting, routing analysis, security threat detection, and AI-powered insights. Built specifically for DNS engineering interviews and network troubleshooting scenarios.

## Features

### 1. DNS Troubleshooting
- **Query/Response Analysis**: Track DNS queries and their responses with timing
- **Failed Queries Detection**: Identify NXDOMAIN, SERVFAIL, and other DNS errors
- **DNS Server Performance**: Measure response times and identify slow servers
- **Slow Query Detection**: Highlight queries taking longer than 100ms
- **Server Statistics**: Compare performance across multiple DNS servers

### 2. Routing Protocol Analysis
- **OSPF Detection**: Identify OSPF packets and discover neighbors
- **BGP Analysis**: Detect BGP sessions and peer relationships
- **Asymmetric Routing**: Identify unbalanced traffic flows between endpoints
- **Routing Loop Detection**: Analyze packet paths for potential loops

### 3. Network Security
- **Port Scan Detection**: Identify hosts scanning multiple ports
- **DDoS Pattern Recognition**: Detect high-volume traffic from single sources
- **Firewall/ACL Analysis**: Track TCP RST and ICMP unreachable messages
- **SYN Flood Detection**: Identify SYN attacks with high SYN/ACK ratios
- **Attack Severity Classification**: Categorize threats by severity level

### 4. ICMP & Traceroute Analysis
- **Ping Statistics**: Calculate average latency and packet loss
- **Traceroute Visualization**: Map network hops with timing data
- **Unreachable Destinations**: Track ICMP unreachable messages
- **Network Path Analysis**: Identify routing issues and bottlenecks

### 5. Traffic Analysis
- **Protocol Distribution**: Pie charts showing traffic breakdown by protocol
- **Top Talkers**: Identify hosts generating the most traffic
- **Port Statistics**: Most commonly used destination ports
- **Conversation Analysis**: Track top conversations by packet count and bytes

### 6. Misbehaving Resources
- **TCP Retransmission Detection**: Identify hosts with high retransmission rates
- **Connection Timeouts**: Track failed connection attempts
- **Performance Issues**: Highlight problematic network endpoints

### 7. AI-Powered Insights (OpenAI Integration)
- **Intelligent Analysis**: GPT-4 analyzes findings and provides context
- **Root Cause Identification**: AI suggests likely causes of issues
- **Remediation Recommendations**: Actionable steps to fix problems
- **Troubleshooting Steps**: Step-by-step guides for common issues
- **Severity Assessment**: Automatic classification (low/medium/high/critical)

## Installation

### Prerequisites

1. **Python 3.7+** with scapy library:
```bash
pip3 install scapy
```

2. **PHP 7.4+** with curl extension

3. **OpenAI API Key** (optional, for AI insights):
- Sign up at https://platform.openai.com/
- Generate an API key
- Add to `/Users/ryan/development/veribits.com/app/.env`:
```
OPENAI_API_KEY=sk-your-key-here
```

### Files Created

```
/Users/ryan/development/veribits.com/
├── scripts/
│   ├── pcap_analyzer.py              # Python PCAP analysis engine
│   └── generate_sample_pcap.py       # Sample PCAP generator
├── app/
│   ├── src/Controllers/
│   │   └── PcapAnalyzerController.php # API controller
│   ├── public/
│   │   ├── tool/
│   │   │   └── pcap-analyzer.php     # Frontend interface
│   │   └── index.php                 # API routes (updated)
│   └── .env                          # Environment config (updated)
└── samples/pcap/                     # Sample PCAP files (generated)
```

## Usage

### 1. Generate Sample PCAP Files (Optional)

```bash
cd /Users/ryan/development/veribits.com
python3 scripts/generate_sample_pcap.py
```

This creates 5 sample PCAP files in `/Users/ryan/development/veribits.com/samples/pcap/`:
- `dns_issues.pcap` - DNS troubleshooting scenarios
- `security_threats.pcap` - Port scans, DDoS, firewall blocks
- `routing_traffic.pcap` - OSPF, BGP, asymmetric routing
- `icmp_traceroute.pcap` - Ping, traceroute, unreachable
- `comprehensive_analysis.pcap` - All scenarios combined

### 2. Access the Tool

Navigate to: `http://your-veribits-domain/tool/pcap-analyzer.php`

### 3. Upload & Analyze

1. **Drag & drop** a PCAP file or **click to browse**
2. **Enable AI Insights** (requires OpenAI API key)
3. Click **Analyze PCAP File**
4. View comprehensive results with visualizations

### 4. API Endpoint

**Endpoint**: `POST /api/v1/tools/pcap-analyze`

**Request**:
- Method: POST
- Content-Type: multipart/form-data
- Headers: `X-API-Key: your-api-key` (optional)

**Form Data**:
- `pcap_file`: The PCAP file (required)
- `use_ai`: "true" or "false" (optional, default: false)

**Response**:
```json
{
  "success": true,
  "message": "PCAP analysis completed",
  "data": {
    "analysis": {
      "metadata": { ... },
      "dns_analysis": { ... },
      "security_analysis": { ... },
      "routing_analysis": { ... },
      "icmp_analysis": { ... },
      "traffic_stats": { ... },
      "misbehaving_resources": { ... },
      "protocol_distribution": { ... },
      "timeline": [ ... ]
    },
    "ai_insights": {
      "summary": "...",
      "root_cause": "...",
      "recommendations": [ ... ],
      "troubleshooting_steps": [ ... ],
      "severity": "medium"
    }
  }
}
```

## Interview Demo Scenarios

### Scenario 1: DNS Resolution Failure

**Use**: `samples/pcap/dns_issues.pcap`

**Expected Findings**:
- Multiple NXDOMAIN responses
- Slow queries (>100ms)
- Queries without responses
- DNS server performance comparison

**AI Insights Will Suggest**:
- Check DNS server configuration
- Verify domain registration
- Review DNS caching settings
- Consider alternative DNS servers

### Scenario 2: Network Security Breach

**Use**: `samples/pcap/security_threats.pcap`

**Expected Findings**:
- Port scan detected (80+ ports scanned)
- DDoS pattern from single source (500+ packets)
- SYN flood attack
- Firewall blocks (ICMP admin prohibited)

**AI Insights Will Suggest**:
- Implement rate limiting
- Block malicious IPs
- Enable SYN cookies
- Review firewall rules

### Scenario 3: Routing Issues

**Use**: `samples/pcap/routing_traffic.pcap`

**Expected Findings**:
- OSPF neighbor relationships
- BGP peering sessions
- Asymmetric routing (90% imbalance)
- Packet path analysis

**AI Insights Will Suggest**:
- Verify routing table symmetry
- Check BGP path preferences
- Review OSPF costs
- Investigate network topology

### Scenario 4: Performance Troubleshooting

**Use**: `samples/pcap/comprehensive_analysis.pcap`

**Expected Findings**:
- High retransmission rates
- Connection timeouts
- Protocol distribution anomalies
- Top talkers and bandwidth hogs

**AI Insights Will Suggest**:
- Investigate network congestion
- Check MTU/MSS settings
- Review QoS policies
- Analyze application behavior

## Visualizations

The tool provides interactive charts using Chart.js:

1. **DNS Server Performance** - Bar chart comparing query counts
2. **Protocol Distribution** - Pie chart showing traffic breakdown
3. **Top Destination Ports** - Bar chart of most-used ports
4. **Timeline View** - Packet events over time

All sections are collapsible for better navigation.

## Export Options

- **JSON Export**: Download complete analysis results as JSON
- **PDF Report**: Generate formatted PDF report (button included)
- **Raw Data**: Access all analysis data programmatically via API

## Performance Considerations

- **File Size Limit**: 100MB per PCAP file
- **Processing Time**: ~1-5 seconds for typical captures
- **AI Analysis**: Additional 2-5 seconds for OpenAI processing
- **Rate Limiting**: Anonymous users limited by IP address

## Troubleshooting

### "scapy library not available"

Install scapy:
```bash
pip3 install scapy
```

### "Python not found"

Ensure Python 3 is installed:
```bash
python3 --version
```

### "AI analysis not available"

Add OpenAI API key to `.env`:
```bash
OPENAI_API_KEY=sk-your-key-here
```

### "Failed to parse analyzer output"

Check Python script permissions:
```bash
chmod +x /Users/ryan/development/veribits.com/scripts/pcap_analyzer.py
```

## Security Features

- **File Type Validation**: Only accepts .pcap, .pcapng, .cap files
- **Size Limits**: 100MB maximum to prevent DoS
- **Input Sanitization**: All file paths are escaped
- **Rate Limiting**: Protects against abuse
- **Temporary File Cleanup**: Uploaded files are automatically deleted
- **API Key Authentication**: Optional authentication for API access

## DNS Engineering Interview Highlights

### Key Talking Points

1. **Deep Protocol Knowledge**:
   - DNS query/response matching with ID tracking
   - Understanding of DNS error codes (NXDOMAIN, SERVFAIL, etc.)
   - DNSSEC validation awareness

2. **Performance Analysis**:
   - Response time measurement and SLA compliance
   - Query distribution across servers
   - Slow query identification and optimization

3. **Troubleshooting Methodology**:
   - Systematic analysis from metadata to specific protocols
   - Root cause identification using AI and pattern recognition
   - Actionable remediation steps

4. **Security Awareness**:
   - DNS amplification attack detection
   - Malicious query pattern recognition
   - Firewall/ACL effectiveness analysis

5. **Modern Tech Stack**:
   - Python for packet processing (scapy)
   - PHP for backend API
   - AI integration (OpenAI GPT-4)
   - Modern frontend with Chart.js visualizations

## Advanced Features for Demo

### Real-time Analysis
The tool processes large PCAPs efficiently using optimized algorithms:
- Streaming packet processing
- Memory-efficient data structures
- Selective sampling for visualization

### Comprehensive Coverage
Analyzes multiple protocol layers:
- Layer 2: Ethernet frames
- Layer 3: IP, ICMP, routing protocols
- Layer 4: TCP, UDP
- Layer 7: DNS, BGP, OSPF

### Production-Ready Code
- Error handling at every level
- Logging for debugging
- Rate limiting for API protection
- Input validation and sanitization
- Scalable architecture

## Future Enhancements

Potential additions to discuss in interview:
- Real-time packet capture integration
- Historical analysis and trending
- Automated alerting and notifications
- Integration with SIEM systems
- Machine learning for anomaly detection
- Support for encrypted traffic analysis (TLS inspection)
- Custom rule engine for detection

## License

© 2025 VeriBits by After Dark Systems. All rights reserved.

## Support

For questions or issues:
- Email: support@veribits.com
- Documentation: https://veribits.com/docs
- GitHub: https://github.com/afterdarksys/veribits

---

**Built for DNS Engineering Excellence** 🚀
