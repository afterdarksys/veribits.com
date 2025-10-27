#!/usr/bin/env python3
"""
Generate sample PCAP files for testing the PCAP Analyzer
Creates realistic network scenarios with DNS, security issues, and routing traffic
"""

import sys

try:
    from scapy.all import (
        IP, TCP, UDP, ICMP, DNS, DNSQR, DNSRR, Ether,
        wrpcap, Raw
    )
    SCAPY_AVAILABLE = True
except ImportError:
    print("Error: scapy library not available. Install with: pip3 install scapy")
    sys.exit(1)

import random
import time
from datetime import datetime


def generate_dns_troubleshooting_pcap(filename='dns_issues.pcap'):
    """Generate PCAP with various DNS issues"""
    print(f"Generating {filename} - DNS troubleshooting scenario...")

    packets = []
    dns_server = "8.8.8.8"
    client = "192.168.1.100"

    # Normal DNS queries
    for i in range(20):
        query = Ether()/IP(src=client, dst=dns_server)/UDP(sport=random.randint(49152, 65535), dport=53)/DNS(
            id=i,
            qr=0,
            qd=DNSQR(qname=f"example{i}.com")
        )
        packets.append(query)

        # Response (50% success rate)
        if random.random() > 0.3:
            response = Ether()/IP(src=dns_server, dst=client)/UDP(sport=53, dport=query[UDP].sport)/DNS(
                id=i,
                qr=1,
                an=DNSRR(rrname=f"example{i}.com", rdata="93.184.216.34")
            )
            packets.append(response)

    # Failed queries (NXDOMAIN)
    for i in range(5):
        query = Ether()/IP(src=client, dst=dns_server)/UDP(sport=random.randint(49152, 65535), dport=53)/DNS(
            id=100+i,
            qr=0,
            qd=DNSQR(qname=f"nonexistent{i}.invalid")
        )
        packets.append(query)

        response = Ether()/IP(src=dns_server, dst=client)/UDP(sport=53, dport=query[UDP].sport)/DNS(
            id=100+i,
            qr=1,
            rcode=3  # NXDOMAIN
        )
        packets.append(response)

    # Slow DNS queries (simulate delays)
    for i in range(3):
        query = Ether()/IP(src=client, dst=dns_server)/UDP(sport=random.randint(49152, 65535), dport=53)/DNS(
            id=200+i,
            qr=0,
            qd=DNSQR(qname=f"slow{i}.example.com")
        )
        packets.append(query)

        # Add delay simulation by spacing packets
        time.sleep(0.15)

        response = Ether()/IP(src=dns_server, dst=client)/UDP(sport=53, dport=query[UDP].sport)/DNS(
            id=200+i,
            qr=1,
            an=DNSRR(rrname=f"slow{i}.example.com", rdata="1.2.3.4")
        )
        packets.append(response)

    wrpcap(filename, packets)
    print(f"✓ Created {filename} with {len(packets)} packets")


def generate_security_threats_pcap(filename='security_threats.pcap'):
    """Generate PCAP with security threats: port scans, DDoS attempts"""
    print(f"Generating {filename} - Security threats scenario...")

    packets = []
    attacker = "203.0.113.50"
    target = "192.168.1.10"

    # Port scan
    print("  - Adding port scan...")
    for port in range(20, 100):
        syn = Ether()/IP(src=attacker, dst=target)/TCP(sport=random.randint(49152, 65535), dport=port, flags='S')
        packets.append(syn)

        # Some RST responses (blocked ports)
        if random.random() > 0.2:
            rst = Ether()/IP(src=target, dst=attacker)/TCP(sport=port, dport=syn[TCP].sport, flags='R')
            packets.append(rst)

    # DDoS-like traffic (high volume from single source)
    print("  - Adding DDoS pattern...")
    for i in range(500):
        pkt = Ether()/IP(src=attacker, dst=target)/TCP(
            sport=random.randint(1024, 65535),
            dport=80,
            flags='S'
        )
        packets.append(pkt)

    # SYN flood
    print("  - Adding SYN flood...")
    for i in range(100):
        syn = Ether()/IP(src=f"198.51.100.{random.randint(1, 254)}", dst=target)/TCP(
            sport=random.randint(1024, 65535),
            dport=443,
            flags='S'
        )
        packets.append(syn)
        # No responses (flooded server)

    # ACL blocks (ICMP unreachable)
    print("  - Adding firewall blocks...")
    for i in range(10):
        blocked = Ether()/IP(src="192.168.1.100", dst="10.0.0.50")/TCP(sport=random.randint(49152, 65535), dport=22, flags='S')
        packets.append(blocked)

        icmp_unreach = Ether()/IP(src="192.168.1.1", dst="192.168.1.100")/ICMP(type=3, code=13)  # Admin prohibited
        packets.append(icmp_unreach)

    wrpcap(filename, packets)
    print(f"✓ Created {filename} with {len(packets)} packets")


def generate_routing_pcap(filename='routing_traffic.pcap'):
    """Generate PCAP with routing protocols (OSPF, BGP) and asymmetric flows"""
    print(f"Generating {filename} - Routing protocols scenario...")

    packets = []

    # OSPF traffic (IP protocol 89)
    print("  - Adding OSPF packets...")
    ospf_routers = ["10.0.0.1", "10.0.0.2", "10.0.0.3"]
    for i in range(20):
        src = random.choice(ospf_routers)
        dst = "224.0.0.5"  # OSPF multicast
        ospf = Ether()/IP(src=src, dst=dst, proto=89)/Raw(load=b'OSPF_HELLO')
        packets.append(ospf)

    # BGP traffic (TCP port 179)
    print("  - Adding BGP packets...")
    bgp_peers = [("192.0.2.1", "192.0.2.2"), ("198.51.100.1", "198.51.100.2")]
    for peer1, peer2 in bgp_peers:
        # BGP session establishment
        syn = Ether()/IP(src=peer1, dst=peer2)/TCP(sport=random.randint(49152, 65535), dport=179, flags='S')
        packets.append(syn)

        syn_ack = Ether()/IP(src=peer2, dst=peer1)/TCP(sport=179, dport=syn[TCP].sport, flags='SA')
        packets.append(syn_ack)

        ack = Ether()/IP(src=peer1, dst=peer2)/TCP(sport=syn[TCP].sport, dport=179, flags='A')
        packets.append(ack)

        # BGP keepalives
        for i in range(5):
            bgp_pkt = Ether()/IP(src=peer1, dst=peer2)/TCP(sport=syn[TCP].sport, dport=179, flags='PA')/Raw(load=b'BGP_KEEPALIVE')
            packets.append(bgp_pkt)

    # Asymmetric routing (more packets in one direction)
    print("  - Adding asymmetric flows...")
    client = "192.168.1.50"
    server = "203.0.113.100"

    # 100 packets from client to server
    for i in range(100):
        pkt = Ether()/IP(src=client, dst=server)/TCP(sport=random.randint(49152, 65535), dport=80, flags='PA')/Raw(load=b'GET / HTTP/1.1')
        packets.append(pkt)

    # Only 10 packets from server to client (asymmetric)
    for i in range(10):
        pkt = Ether()/IP(src=server, dst=client)/TCP(sport=80, dport=random.randint(49152, 65535), flags='PA')/Raw(load=b'HTTP/1.1 200 OK')
        packets.append(pkt)

    wrpcap(filename, packets)
    print(f"✓ Created {filename} with {len(packets)} packets")


def generate_icmp_traceroute_pcap(filename='icmp_traceroute.pcap'):
    """Generate PCAP with ICMP and traceroute activity"""
    print(f"Generating {filename} - ICMP/Traceroute scenario...")

    packets = []
    source = "192.168.1.100"
    destination = "8.8.8.8"

    # Normal ping
    print("  - Adding ping requests/replies...")
    for i in range(10):
        ping_req = Ether()/IP(src=source, dst=destination)/ICMP(type=8, id=1, seq=i)
        packets.append(ping_req)

        ping_reply = Ether()/IP(src=destination, dst=source)/ICMP(type=0, id=1, seq=i)
        packets.append(ping_reply)

    # Traceroute simulation
    print("  - Adding traceroute hops...")
    hops = ["192.168.1.1", "10.0.0.1", "172.16.0.1", "203.0.113.1", "8.8.8.8"]

    for ttl, hop in enumerate(hops, start=1):
        # Send packet with increasing TTL
        trace_pkt = Ether()/IP(src=source, dst=destination, ttl=ttl)/UDP(sport=33434, dport=33434)
        packets.append(trace_pkt)

        if ttl < len(hops):
            # Time exceeded from intermediate hop
            time_exceeded = Ether()/IP(src=hop, dst=source)/ICMP(type=11, code=0)
            packets.append(time_exceeded)
        else:
            # Final destination reached
            dest_unreach = Ether()/IP(src=hop, dst=source)/ICMP(type=3, code=3)  # Port unreachable
            packets.append(dest_unreach)

    # Unreachable destinations
    print("  - Adding unreachable destinations...")
    for i in range(5):
        pkt = Ether()/IP(src=source, dst=f"10.255.255.{i}")/ICMP(type=8)
        packets.append(pkt)

        unreach = Ether()/IP(src="192.168.1.1", dst=source)/ICMP(type=3, code=1)  # Host unreachable
        packets.append(unreach)

    wrpcap(filename, packets)
    print(f"✓ Created {filename} with {len(packets)} packets")


def generate_comprehensive_pcap(filename='comprehensive_analysis.pcap'):
    """Generate a comprehensive PCAP with all scenarios combined"""
    print(f"Generating {filename} - Comprehensive scenario...")

    all_packets = []

    # Mix all scenarios
    print("  - Combining all scenarios...")

    # Add some of each type
    temp_files = []

    generate_dns_troubleshooting_pcap('/tmp/dns_temp.pcap')
    generate_security_threats_pcap('/tmp/security_temp.pcap')
    generate_routing_pcap('/tmp/routing_temp.pcap')
    generate_icmp_traceroute_pcap('/tmp/icmp_temp.pcap')

    # Read and combine
    from scapy.all import rdpcap

    for temp_file in ['/tmp/dns_temp.pcap', '/tmp/security_temp.pcap', '/tmp/routing_temp.pcap', '/tmp/icmp_temp.pcap']:
        try:
            pkts = rdpcap(temp_file)
            all_packets.extend(pkts)
        except:
            pass

    # Shuffle for realistic capture
    random.shuffle(all_packets)

    wrpcap(filename, all_packets)
    print(f"✓ Created {filename} with {len(all_packets)} packets")


def main():
    print("=" * 60)
    print("PCAP Sample Generator for VeriBits PCAP Analyzer")
    print("=" * 60)
    print()

    # Create sample directory
    import os
    sample_dir = "/Users/ryan/development/veribits.com/samples/pcap"
    os.makedirs(sample_dir, exist_ok=True)

    # Generate all sample PCAPs
    generate_dns_troubleshooting_pcap(f"{sample_dir}/dns_issues.pcap")
    print()
    generate_security_threats_pcap(f"{sample_dir}/security_threats.pcap")
    print()
    generate_routing_pcap(f"{sample_dir}/routing_traffic.pcap")
    print()
    generate_icmp_traceroute_pcap(f"{sample_dir}/icmp_traceroute.pcap")
    print()
    generate_comprehensive_pcap(f"{sample_dir}/comprehensive_analysis.pcap")

    print()
    print("=" * 60)
    print("✓ All sample PCAP files generated successfully!")
    print(f"Location: {sample_dir}")
    print("=" * 60)
    print()
    print("Sample files created:")
    print("  1. dns_issues.pcap - DNS troubleshooting scenarios")
    print("  2. security_threats.pcap - Port scans, DDoS, firewall blocks")
    print("  3. routing_traffic.pcap - OSPF, BGP, asymmetric routing")
    print("  4. icmp_traceroute.pcap - Ping, traceroute, unreachable")
    print("  5. comprehensive_analysis.pcap - All scenarios combined")
    print()


if __name__ == '__main__':
    main()
