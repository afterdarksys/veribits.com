import {
    IExecuteFunctions,
    INodeExecutionData,
    INodeType,
    INodeTypeDescription,
    NodeOperationError,
} from 'n8n-workflow';

export class VeriBits implements INodeType {
    description: INodeTypeDescription = {
        displayName: 'VeriBits',
        name: 'veribits',
        icon: 'file:veribits.svg',
        group: ['transform'],
        version: 1,
        subtitle: '={{$parameter["operation"]}}',
        description: 'Security and verification tools',
        defaults: {
            name: 'VeriBits',
        },
        inputs: ['main'],
        outputs: ['main'],
        credentials: [
            {
                name: 'veribitsApi',
                required: true,
            },
        ],
        properties: [
            {
                displayName: 'Operation',
                name: 'operation',
                type: 'options',
                noDataExpression: true,
                options: [
                    // File Verification
                    {
                        name: 'Verify File Hash',
                        value: 'verifyFile',
                        description: 'Verify a file hash against known databases',
                        action: 'Verify file hash',
                    },
                    // DNS Tools
                    {
                        name: 'DNS Lookup',
                        value: 'dnsLookup',
                        description: 'Query DNS records for a domain',
                        action: 'DNS lookup',
                    },
                    {
                        name: 'DNS Propagation',
                        value: 'dnsPropagation',
                        description: 'Check DNS propagation globally',
                        action: 'DNS propagation check',
                    },
                    {
                        name: 'DNSSEC Validate',
                        value: 'dnssecValidate',
                        description: 'Validate DNSSEC configuration',
                        action: 'DNSSEC validation',
                    },
                    {
                        name: 'Reverse DNS',
                        value: 'reverseDns',
                        description: 'Perform reverse DNS lookup',
                        action: 'Reverse DNS lookup',
                    },
                    // SSL Tools
                    {
                        name: 'SSL Validate',
                        value: 'sslValidate',
                        description: 'Validate SSL certificate',
                        action: 'SSL validation',
                    },
                    {
                        name: 'SSL Chain',
                        value: 'sslChain',
                        description: 'Resolve SSL certificate chain',
                        action: 'SSL chain resolution',
                    },
                    // Security Tools
                    {
                        name: 'Secrets Scan',
                        value: 'secretsScan',
                        description: 'Scan code for exposed secrets',
                        action: 'Secrets scan',
                    },
                    {
                        name: 'IAM Policy Analyze',
                        value: 'iamAnalyze',
                        description: 'Analyze IAM policy for security issues',
                        action: 'IAM policy analysis',
                    },
                    {
                        name: 'Hash Lookup',
                        value: 'hashLookup',
                        description: 'Look up hash in breach databases',
                        action: 'Hash lookup',
                    },
                    // Developer Tools
                    {
                        name: 'Generate Hash',
                        value: 'generateHash',
                        description: 'Generate hash from input',
                        action: 'Generate hash',
                    },
                    {
                        name: 'JWT Decode',
                        value: 'jwtDecode',
                        description: 'Decode JWT token',
                        action: 'JWT decode',
                    },
                    {
                        name: 'Regex Test',
                        value: 'regexTest',
                        description: 'Test regular expression',
                        action: 'Regex test',
                    },
                    {
                        name: 'IP Calculate',
                        value: 'ipCalculate',
                        description: 'Calculate IP/CIDR information',
                        action: 'IP calculation',
                    },
                ],
                default: 'dnsLookup',
            },
            // DNS Lookup parameters
            {
                displayName: 'Domain',
                name: 'domain',
                type: 'string',
                default: '',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['dnsLookup', 'dnsPropagation', 'dnssecValidate', 'sslValidate', 'sslChain'],
                    },
                },
                description: 'Domain name to query',
            },
            {
                displayName: 'Record Type',
                name: 'recordType',
                type: 'options',
                options: [
                    { name: 'A', value: 'A' },
                    { name: 'AAAA', value: 'AAAA' },
                    { name: 'MX', value: 'MX' },
                    { name: 'TXT', value: 'TXT' },
                    { name: 'NS', value: 'NS' },
                    { name: 'CNAME', value: 'CNAME' },
                    { name: 'SOA', value: 'SOA' },
                ],
                default: 'A',
                displayOptions: {
                    show: {
                        operation: ['dnsLookup', 'dnsPropagation'],
                    },
                },
            },
            // IP for reverse DNS
            {
                displayName: 'IP Address',
                name: 'ip',
                type: 'string',
                default: '',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['reverseDns', 'ipCalculate'],
                    },
                },
            },
            // Hash parameters
            {
                displayName: 'Hash',
                name: 'hash',
                type: 'string',
                default: '',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['verifyFile', 'hashLookup'],
                    },
                },
                description: 'SHA256 hash to verify/lookup',
            },
            // Generate hash parameters
            {
                displayName: 'Input',
                name: 'input',
                type: 'string',
                default: '',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['generateHash'],
                    },
                },
            },
            {
                displayName: 'Algorithm',
                name: 'algorithm',
                type: 'options',
                options: [
                    { name: 'MD5', value: 'md5' },
                    { name: 'SHA1', value: 'sha1' },
                    { name: 'SHA256', value: 'sha256' },
                    { name: 'SHA512', value: 'sha512' },
                ],
                default: 'sha256',
                displayOptions: {
                    show: {
                        operation: ['generateHash'],
                    },
                },
            },
            // Secrets scan
            {
                displayName: 'Content',
                name: 'content',
                type: 'string',
                typeOptions: {
                    rows: 10,
                },
                default: '',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['secretsScan'],
                    },
                },
                description: 'Code or config content to scan',
            },
            // IAM Policy
            {
                displayName: 'Policy JSON',
                name: 'policy',
                type: 'json',
                default: '{}',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['iamAnalyze'],
                    },
                },
            },
            // JWT
            {
                displayName: 'Token',
                name: 'token',
                type: 'string',
                default: '',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['jwtDecode'],
                    },
                },
            },
            // Regex
            {
                displayName: 'Pattern',
                name: 'pattern',
                type: 'string',
                default: '',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['regexTest'],
                    },
                },
            },
            {
                displayName: 'Test String',
                name: 'testString',
                type: 'string',
                default: '',
                required: true,
                displayOptions: {
                    show: {
                        operation: ['regexTest'],
                    },
                },
            },
        ],
    };

    async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
        const items = this.getInputData();
        const returnData: INodeExecutionData[] = [];

        const credentials = await this.getCredentials('veribitsApi');
        const apiKey = credentials.apiKey as string;
        const baseUrl = (credentials.baseUrl as string) || 'https://veribits.com/api/v1';

        for (let i = 0; i < items.length; i++) {
            try {
                const operation = this.getNodeParameter('operation', i) as string;
                let endpoint = '';
                let body: Record<string, any> = {};

                switch (operation) {
                    case 'dnsLookup':
                        endpoint = '/dns/check';
                        body = {
                            domain: this.getNodeParameter('domain', i) as string,
                            record_type: this.getNodeParameter('recordType', i) as string,
                        };
                        break;

                    case 'dnsPropagation':
                        endpoint = '/tools/dns-propagation';
                        body = {
                            domain: this.getNodeParameter('domain', i) as string,
                            record_type: this.getNodeParameter('recordType', i) as string,
                        };
                        break;

                    case 'dnssecValidate':
                        endpoint = '/tools/dnssec-validate';
                        body = {
                            domain: this.getNodeParameter('domain', i) as string,
                        };
                        break;

                    case 'reverseDns':
                        endpoint = '/tools/reverse-dns';
                        body = {
                            ip: this.getNodeParameter('ip', i) as string,
                        };
                        break;

                    case 'sslValidate':
                        endpoint = '/ssl/validate';
                        body = {
                            host: this.getNodeParameter('domain', i) as string,
                        };
                        break;

                    case 'sslChain':
                        endpoint = '/ssl/resolve-chain';
                        body = {
                            host: this.getNodeParameter('domain', i) as string,
                        };
                        break;

                    case 'verifyFile':
                        endpoint = '/verify/file';
                        body = {
                            hash: this.getNodeParameter('hash', i) as string,
                        };
                        break;

                    case 'hashLookup':
                        endpoint = '/tools/hash-lookup';
                        body = {
                            hash: this.getNodeParameter('hash', i) as string,
                        };
                        break;

                    case 'generateHash':
                        endpoint = '/tools/generate-hash';
                        body = {
                            input: this.getNodeParameter('input', i) as string,
                            algorithm: this.getNodeParameter('algorithm', i) as string,
                        };
                        break;

                    case 'secretsScan':
                        endpoint = '/security/secrets/scan';
                        body = {
                            content: this.getNodeParameter('content', i) as string,
                        };
                        break;

                    case 'iamAnalyze':
                        endpoint = '/security/iam-policy/analyze';
                        body = {
                            policy: JSON.parse(this.getNodeParameter('policy', i) as string),
                        };
                        break;

                    case 'jwtDecode':
                        endpoint = '/jwt/decode';
                        body = {
                            token: this.getNodeParameter('token', i) as string,
                        };
                        break;

                    case 'regexTest':
                        endpoint = '/tools/regex-test';
                        body = {
                            pattern: this.getNodeParameter('pattern', i) as string,
                            input: this.getNodeParameter('testString', i) as string,
                        };
                        break;

                    case 'ipCalculate':
                        endpoint = '/tools/ip-calculate';
                        body = {
                            ip: this.getNodeParameter('ip', i) as string,
                        };
                        break;

                    default:
                        throw new NodeOperationError(this.getNode(), `Unknown operation: ${operation}`);
                }

                const response = await this.helpers.httpRequest({
                    method: 'POST',
                    url: `${baseUrl}${endpoint}`,
                    body,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': apiKey,
                    },
                    json: true,
                });

                returnData.push({
                    json: response,
                    pairedItem: { item: i },
                });

            } catch (error) {
                if (this.continueOnFail()) {
                    returnData.push({
                        json: { error: (error as Error).message },
                        pairedItem: { item: i },
                    });
                    continue;
                }
                throw error;
            }
        }

        return [returnData];
    }
}
