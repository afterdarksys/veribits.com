#!/usr/bin/env python3
"""
AI-Powered PCAP Analyzer for VeriBits
Analyzes network packet captures for DNS, routing, security issues
"""

import sys
import json
from datetime import datetime
from collections import defaultdict, Counter
from typing import Dict, List, Any, Optional
import os

try:
    from scapy.all import rdpcap, IP, TCP, UDP, ICMP, DNS, DNSQR, DNSRR
    from scapy.layers.l2 import Ether
    SCAPY_AVAILABLE = True
except ImportError:
    SCAPY_AVAILABLE = False


class PcapAnalyzer:
    """Comprehensive PCAP analyzer with DNS, routing, and security analysis"""

    def __init__(self, pcap_file: str):
        self.pcap_file = pcap_file
        self.packets = []
        self.results = {
            'metadata': {},
            'dns_analysis': {},
            'routing_analysis': {},
            'icmp_analysis': {},
            'security_analysis': {},
            'traffic_stats': {},
            'misbehaving_resources': {},
            'protocol_distribution': {},
            'timeline': []
        }

    def analyze(self) -> Dict[str, Any]:
        """Main analysis function"""
        if not SCAPY_AVAILABLE:
            return {
                'error': 'scapy library not available. Install with: pip3 install scapy',
                'success': False
            }

        try:
            # Load PCAP file
            self.packets = rdpcap(self.pcap_file)

            # Extract metadata
            self._extract_metadata()

            # Run all analyses
            self._analyze_dns()
            self._analyze_routing()
            self._analyze_icmp()
            self._analyze_security()
            self._analyze_traffic()
            self._analyze_misbehaving_resources()
            self._generate_timeline()

            self.results['success'] = True
            return self.results

        except Exception as e:
            return {
                'error': str(e),
                'success': False
            }

    def _extract_metadata(self):
        """Extract basic PCAP metadata"""
        if not self.packets:
            return

        first_packet_time = float(self.packets[0].time)
        last_packet_time = float(self.packets[-1].time)

        self.results['metadata'] = {
            'total_packets': len(self.packets),
            'file_size_bytes': os.path.getsize(self.pcap_file),
            'capture_duration': last_packet_time - first_packet_time,
            'start_time': datetime.fromtimestamp(first_packet_time).isoformat(),
            'end_time': datetime.fromtimestamp(last_packet_time).isoformat(),
            'packets_per_second': len(self.packets) / max(last_packet_time - first_packet_time, 1)
        }

    def _analyze_dns(self):
        """Analyze DNS queries and responses"""
        dns_queries = []
        dns_responses = []
        failed_queries = []
        dns_servers = Counter()
        query_response_map = {}

        for pkt in self.packets:
            if pkt.haslayer(DNS):
                dns_layer = pkt[DNS]
                timestamp = float(pkt.time)

                # DNS Query
                if dns_layer.qr == 0 and pkt.haslayer(DNSQR):
                    query_name = pkt[DNSQR].qname.decode('utf-8', errors='ignore') if isinstance(pkt[DNSQR].qname, bytes) else str(pkt[DNSQR].qname)
                    query_type = pkt[DNSQR].qtype

                    query_info = {
                        'timestamp': timestamp,
                        'query_name': query_name,
                        'query_type': self._dns_type_to_string(query_type),
                        'query_id': dns_layer.id,
                        'src_ip': pkt[IP].src if pkt.haslayer(IP) else None,
                        'dst_ip': pkt[IP].dst if pkt.haslayer(IP) else None
                    }
                    dns_queries.append(query_info)

                    if pkt.haslayer(IP):
                        dns_servers[pkt[IP].dst] += 1

                    # Store for response matching
                    query_response_map[dns_layer.id] = {
                        'query': query_info,
                        'response': None,
                        'response_time': None
                    }

                # DNS Response
                elif dns_layer.qr == 1:
                    response_code = dns_layer.rcode
                    query_id = dns_layer.id

                    response_info = {
                        'timestamp': timestamp,
                        'query_id': query_id,
                        'response_code': response_code,
                        'response_code_name': self._dns_rcode_to_string(response_code),
                        'answer_count': dns_layer.ancount,
                        'authority_count': dns_layer.nscount,
                        'additional_count': dns_layer.arcount,
                        'src_ip': pkt[IP].src if pkt.haslayer(IP) else None,
                        'answers': []
                    }

                    # Extract answers
                    if dns_layer.ancount > 0 and pkt.haslayer(DNSRR):
                        for i in range(dns_layer.ancount):
                            try:
                                rr = pkt[DNSRR][i] if dns_layer.ancount > 1 else pkt[DNSRR]
                                answer_data = {
                                    'name': rr.rrname.decode('utf-8', errors='ignore') if isinstance(rr.rrname, bytes) else str(rr.rrname),
                                    'type': self._dns_type_to_string(rr.type),
                                    'ttl': rr.ttl,
                                    'rdata': str(rr.rdata)
                                }
                                response_info['answers'].append(answer_data)
                            except:
                                pass

                    dns_responses.append(response_info)

                    # Match with query
                    if query_id in query_response_map:
                        query_response_map[query_id]['response'] = response_info
                        query_response_map[query_id]['response_time'] = timestamp - query_response_map[query_id]['query']['timestamp']

                        # Check for failures
                        if response_code != 0:  # NOERROR = 0
                            failed_queries.append({
                                'query': query_response_map[query_id]['query']['query_name'],
                                'query_type': query_response_map[query_id]['query']['query_type'],
                                'error_code': response_code,
                                'error_name': self._dns_rcode_to_string(response_code),
                                'timestamp': timestamp,
                                'dns_server': query_response_map[query_id]['query']['dst_ip']
                            })

        # Calculate DNS performance metrics
        response_times = [v['response_time'] for v in query_response_map.values() if v['response_time'] is not None]
        avg_response_time = sum(response_times) / len(response_times) if response_times else 0

        # Identify slow queries (> 100ms)
        slow_queries = [
            {
                'query_name': v['query']['query_name'],
                'response_time': v['response_time'],
                'dns_server': v['query']['dst_ip']
            }
            for v in query_response_map.values()
            if v['response_time'] and v['response_time'] > 0.1
        ]

        self.results['dns_analysis'] = {
            'total_queries': len(dns_queries),
            'total_responses': len(dns_responses),
            'failed_queries': failed_queries,
            'failed_query_count': len(failed_queries),
            'dns_servers': [{'ip': ip, 'query_count': count} for ip, count in dns_servers.most_common()],
            'average_response_time_ms': avg_response_time * 1000,
            'slow_queries': slow_queries[:10],  # Top 10 slowest
            'queries_without_response': len([v for v in query_response_map.values() if v['response'] is None]),
            'query_response_pairs': len([v for v in query_response_map.values() if v['response'] is not None])
        }

    def _analyze_routing(self):
        """Analyze routing protocols (OSPF, BGP)"""
        ospf_packets = []
        bgp_packets = []
        asymmetric_flows = []

        # Track flows for asymmetric routing detection
        flows = defaultdict(lambda: {'src_to_dst': 0, 'dst_to_src': 0})

        for pkt in self.packets:
            # OSPF detection (IP protocol 89)
            if pkt.haslayer(IP) and pkt[IP].proto == 89:
                ospf_packets.append({
                    'timestamp': float(pkt.time),
                    'src_ip': pkt[IP].src,
                    'dst_ip': pkt[IP].dst,
                    'ttl': pkt[IP].ttl
                })

            # BGP detection (TCP port 179)
            if pkt.haslayer(TCP) and (pkt[TCP].sport == 179 or pkt[TCP].dport == 179):
                bgp_packets.append({
                    'timestamp': float(pkt.time),
                    'src_ip': pkt[IP].src,
                    'dst_ip': pkt[IP].dst,
                    'src_port': pkt[TCP].sport,
                    'dst_port': pkt[TCP].dport,
                    'flags': str(pkt[TCP].flags)
                })

            # Track flows for asymmetric routing
            if pkt.haslayer(IP):
                flow_key = tuple(sorted([pkt[IP].src, pkt[IP].dst]))
                if pkt[IP].src < pkt[IP].dst:
                    flows[flow_key]['src_to_dst'] += 1
                else:
                    flows[flow_key]['dst_to_src'] += 1

        # Detect asymmetric flows (significant imbalance)
        for flow_key, counts in flows.items():
            total = counts['src_to_dst'] + counts['dst_to_src']
            if total > 10:  # Only consider flows with sufficient packets
                ratio = abs(counts['src_to_dst'] - counts['dst_to_src']) / total
                if ratio > 0.7:  # More than 70% imbalance
                    asymmetric_flows.append({
                        'endpoints': list(flow_key),
                        'packets_direction_1': counts['src_to_dst'],
                        'packets_direction_2': counts['dst_to_src'],
                        'imbalance_ratio': ratio
                    })

        # Extract OSPF neighbors
        ospf_neighbors = list(set(pkt['src_ip'] for pkt in ospf_packets))

        # Extract BGP peers
        bgp_peers = list(set((pkt['src_ip'], pkt['dst_ip']) for pkt in bgp_packets))

        self.results['routing_analysis'] = {
            'ospf_packets_detected': len(ospf_packets),
            'ospf_neighbors': ospf_neighbors,
            'bgp_packets_detected': len(bgp_packets),
            'bgp_peers': [{'src': peer[0], 'dst': peer[1]} for peer in bgp_peers],
            'asymmetric_flows': asymmetric_flows[:10],  # Top 10
            'asymmetric_routing_detected': len(asymmetric_flows) > 0
        }

    def _analyze_icmp(self):
        """Analyze ICMP packets (ping, traceroute, unreachable)"""
        icmp_packets = []
        ping_requests = []
        ping_replies = []
        unreachable = []
        traceroute_hops = defaultdict(list)

        for pkt in self.packets:
            if pkt.haslayer(ICMP):
                icmp_layer = pkt[ICMP]
                icmp_type = icmp_layer.type
                icmp_code = icmp_layer.code

                packet_info = {
                    'timestamp': float(pkt.time),
                    'src_ip': pkt[IP].src if pkt.haslayer(IP) else None,
                    'dst_ip': pkt[IP].dst if pkt.haslayer(IP) else None,
                    'type': icmp_type,
                    'code': icmp_code,
                    'type_name': self._icmp_type_to_string(icmp_type, icmp_code)
                }
                icmp_packets.append(packet_info)

                # Echo Request (ping)
                if icmp_type == 8:
                    ping_requests.append(packet_info)

                # Echo Reply (pong)
                elif icmp_type == 0:
                    ping_replies.append(packet_info)

                # Destination Unreachable
                elif icmp_type == 3:
                    unreachable.append({
                        **packet_info,
                        'unreachable_type': self._icmp_unreachable_to_string(icmp_code)
                    })

                # Time Exceeded (traceroute)
                elif icmp_type == 11:
                    if pkt.haslayer(IP):
                        ttl = pkt[IP].ttl
                        traceroute_hops[pkt[IP].src].append({
                            'hop': ttl,
                            'ip': pkt[IP].src,
                            'timestamp': float(pkt.time)
                        })

        # Calculate ping statistics
        ping_latencies = []
        request_map = {}
        for req in ping_requests:
            key = (req['src_ip'], req['dst_ip'])
            request_map[key] = req['timestamp']

        for rep in ping_replies:
            key = (rep['dst_ip'], rep['src_ip'])
            if key in request_map:
                latency = (rep['timestamp'] - request_map[key]) * 1000  # ms
                ping_latencies.append(latency)

        avg_latency = sum(ping_latencies) / len(ping_latencies) if ping_latencies else 0

        self.results['icmp_analysis'] = {
            'total_icmp_packets': len(icmp_packets),
            'ping_requests': len(ping_requests),
            'ping_replies': len(ping_replies),
            'average_ping_latency_ms': avg_latency,
            'unreachable_destinations': unreachable[:20],  # Top 20
            'unreachable_count': len(unreachable),
            'traceroute_detected': len(traceroute_hops) > 0,
            'traceroute_hops': dict(traceroute_hops)
        }

    def _analyze_security(self):
        """Detect security issues: port scans, DDoS, ACL blocks, attacks"""
        tcp_rst_count = 0
        tcp_connections = defaultdict(lambda: {'syn': 0, 'ack': 0, 'rst': 0, 'fin': 0})
        port_scan_suspects = defaultdict(set)
        high_volume_sources = Counter()
        acl_blocks = []

        for pkt in self.packets:
            if pkt.haslayer(IP):
                src_ip = pkt[IP].src
                high_volume_sources[src_ip] += 1

                # TCP analysis
                if pkt.haslayer(TCP):
                    dst_port = pkt[TCP].dport
                    flags = pkt[TCP].flags

                    # Track connection states
                    conn_key = (src_ip, pkt[IP].dst)
                    if 'S' in str(flags):
                        tcp_connections[conn_key]['syn'] += 1
                    if 'A' in str(flags):
                        tcp_connections[conn_key]['ack'] += 1
                    if 'R' in str(flags):
                        tcp_connections[conn_key]['rst'] += 1
                        tcp_rst_count += 1
                    if 'F' in str(flags):
                        tcp_connections[conn_key]['fin'] += 1

                    # Port scan detection: many different ports from same source
                    port_scan_suspects[src_ip].add(dst_port)

                    # ACL/Firewall blocks (RST responses)
                    if 'R' in str(flags) and pkt[IP].src != src_ip:
                        acl_blocks.append({
                            'timestamp': float(pkt.time),
                            'blocked_src': src_ip,
                            'blocked_dst': pkt[IP].dst,
                            'blocked_port': dst_port,
                            'reason': 'TCP RST received'
                        })

                # ICMP unreachable = potential firewall block
                if pkt.haslayer(ICMP) and pkt[ICMP].type == 3:
                    acl_blocks.append({
                        'timestamp': float(pkt.time),
                        'blocked_src': src_ip,
                        'blocked_dst': pkt[IP].dst,
                        'reason': 'ICMP Unreachable',
                        'icmp_code': pkt[ICMP].code
                    })

        # Identify port scans (> 20 different ports)
        port_scans = [
            {
                'source_ip': ip,
                'ports_scanned': len(ports),
                'port_list': sorted(list(ports))[:50]  # First 50 ports
            }
            for ip, ports in port_scan_suspects.items()
            if len(ports) > 20
        ]

        # Identify potential DDoS sources (> 1000 packets)
        ddos_suspects = [
            {
                'source_ip': ip,
                'packet_count': count,
                'percentage': (count / len(self.packets)) * 100
            }
            for ip, count in high_volume_sources.most_common(10)
            if count > 1000
        ]

        # SYN flood detection (many SYNs without ACKs)
        syn_floods = [
            {
                'connection': f"{conn[0]} -> {conn[1]}",
                'syn_count': counts['syn'],
                'ack_count': counts['ack'],
                'ratio': counts['syn'] / max(counts['ack'], 1)
            }
            for conn, counts in tcp_connections.items()
            if counts['syn'] > 50 and counts['syn'] / max(counts['ack'], 1) > 5
        ]

        self.results['security_analysis'] = {
            'tcp_rst_count': tcp_rst_count,
            'port_scans_detected': port_scans,
            'port_scan_count': len(port_scans),
            'ddos_suspects': ddos_suspects,
            'ddos_suspect_count': len(ddos_suspects),
            'acl_firewall_blocks': acl_blocks[:50],  # Top 50
            'acl_block_count': len(acl_blocks),
            'syn_flood_detected': syn_floods,
            'syn_flood_count': len(syn_floods)
        }

    def _analyze_traffic(self):
        """Analyze overall traffic patterns and protocol distribution"""
        protocol_stats = Counter()
        port_stats = Counter()
        conversation_stats = defaultdict(lambda: {'packets': 0, 'bytes': 0})

        for pkt in self.packets:
            # Protocol distribution
            if pkt.haslayer(IP):
                proto = pkt[IP].proto
                protocol_stats[self._ip_proto_to_string(proto)] += 1

                # Conversation tracking
                src_ip = pkt[IP].src
                dst_ip = pkt[IP].dst
                conv_key = tuple(sorted([src_ip, dst_ip]))
                conversation_stats[conv_key]['packets'] += 1
                conversation_stats[conv_key]['bytes'] += len(pkt)

            # Port distribution
            if pkt.haslayer(TCP):
                port_stats[pkt[TCP].dport] += 1
            elif pkt.haslayer(UDP):
                port_stats[pkt[UDP].dport] += 1

        # Top conversations
        top_conversations = sorted(
            [
                {
                    'endpoints': list(conv),
                    'packets': stats['packets'],
                    'bytes': stats['bytes']
                }
                for conv, stats in conversation_stats.items()
            ],
            key=lambda x: x['packets'],
            reverse=True
        )[:20]

        self.results['protocol_distribution'] = dict(protocol_stats)
        self.results['traffic_stats'] = {
            'top_ports': [{'port': port, 'count': count} for port, count in port_stats.most_common(20)],
            'top_conversations': top_conversations,
            'unique_ips': len(set(
                [pkt[IP].src for pkt in self.packets if pkt.haslayer(IP)] +
                [pkt[IP].dst for pkt in self.packets if pkt.haslayer(IP)]
            ))
        }

    def _analyze_misbehaving_resources(self):
        """Identify misbehaving resources: retransmissions, timeouts"""
        retransmissions = defaultdict(int)
        connection_timeouts = []
        top_talkers = Counter()

        seen_tcp_segments = set()

        for pkt in self.packets:
            if pkt.haslayer(IP):
                src_ip = pkt[IP].src
                top_talkers[src_ip] += 1

                # TCP retransmission detection
                if pkt.haslayer(TCP):
                    seq = pkt[TCP].seq
                    ack = pkt[TCP].ack
                    segment_key = (src_ip, pkt[IP].dst, pkt[TCP].sport, pkt[TCP].dport, seq)

                    if segment_key in seen_tcp_segments:
                        retransmissions[src_ip] += 1
                    else:
                        seen_tcp_segments.add(segment_key)

        # Top retransmitting hosts
        top_retrans = [
            {
                'ip': ip,
                'retransmission_count': count,
                'retransmission_rate': (count / top_talkers[ip]) * 100 if top_talkers[ip] > 0 else 0
            }
            for ip, count in Counter(retransmissions).most_common(10)
        ]

        self.results['misbehaving_resources'] = {
            'top_talkers': [{'ip': ip, 'packet_count': count} for ip, count in top_talkers.most_common(20)],
            'retransmissions': top_retrans,
            'total_retransmissions': sum(retransmissions.values())
        }

    def _generate_timeline(self):
        """Generate timeline of key events"""
        timeline = []

        if not self.packets:
            return

        start_time = float(self.packets[0].time)

        # Sample packets for timeline (every 100th packet for large captures)
        sample_rate = max(1, len(self.packets) // 1000)

        for i, pkt in enumerate(self.packets[::sample_rate]):
            timestamp = float(pkt.time)
            relative_time = timestamp - start_time

            event = {
                'timestamp': timestamp,
                'relative_time': relative_time,
                'packet_num': i * sample_rate
            }

            # Identify packet type
            if pkt.haslayer(DNS):
                event['type'] = 'DNS'
                if pkt[DNS].qr == 0:
                    event['description'] = 'DNS Query'
                else:
                    event['description'] = 'DNS Response'
            elif pkt.haslayer(ICMP):
                event['type'] = 'ICMP'
                event['description'] = self._icmp_type_to_string(pkt[ICMP].type, pkt[ICMP].code)
            elif pkt.haslayer(TCP):
                event['type'] = 'TCP'
                event['description'] = f"TCP {pkt[TCP].flags}"
            elif pkt.haslayer(UDP):
                event['type'] = 'UDP'
                event['description'] = 'UDP packet'
            else:
                event['type'] = 'Other'
                event['description'] = 'Unknown packet'

            timeline.append(event)

        self.results['timeline'] = timeline

    # Helper methods for protocol/type conversions
    def _dns_type_to_string(self, qtype: int) -> str:
        types = {1: 'A', 2: 'NS', 5: 'CNAME', 6: 'SOA', 12: 'PTR', 15: 'MX', 16: 'TXT', 28: 'AAAA', 33: 'SRV', 257: 'CAA'}
        return types.get(qtype, f'TYPE{qtype}')

    def _dns_rcode_to_string(self, rcode: int) -> str:
        codes = {0: 'NOERROR', 1: 'FORMERR', 2: 'SERVFAIL', 3: 'NXDOMAIN', 4: 'NOTIMP', 5: 'REFUSED'}
        return codes.get(rcode, f'RCODE{rcode}')

    def _icmp_type_to_string(self, icmp_type: int, code: int) -> str:
        types = {
            0: 'Echo Reply',
            3: f'Destination Unreachable ({self._icmp_unreachable_to_string(code)})',
            8: 'Echo Request',
            11: 'Time Exceeded',
            12: 'Parameter Problem'
        }
        return types.get(icmp_type, f'ICMP Type {icmp_type}')

    def _icmp_unreachable_to_string(self, code: int) -> str:
        codes = {
            0: 'Network Unreachable',
            1: 'Host Unreachable',
            2: 'Protocol Unreachable',
            3: 'Port Unreachable',
            4: 'Fragmentation Needed',
            5: 'Source Route Failed',
            13: 'Administratively Prohibited'
        }
        return codes.get(code, f'Code {code}')

    def _ip_proto_to_string(self, proto: int) -> str:
        protocols = {1: 'ICMP', 6: 'TCP', 17: 'UDP', 89: 'OSPF', 47: 'GRE', 50: 'ESP', 51: 'AH'}
        return protocols.get(proto, f'Protocol {proto}')


def main():
    if len(sys.argv) < 2:
        print(json.dumps({
            'error': 'Usage: pcap_analyzer.py <pcap_file>',
            'success': False
        }))
        sys.exit(1)

    pcap_file = sys.argv[1]

    if not os.path.exists(pcap_file):
        print(json.dumps({
            'error': f'PCAP file not found: {pcap_file}',
            'success': False
        }))
        sys.exit(1)

    analyzer = PcapAnalyzer(pcap_file)
    results = analyzer.analyze()

    # Output JSON to stdout
    print(json.dumps(results, indent=2))


if __name__ == '__main__':
    main()
