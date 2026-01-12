/**
 * VeriBits TypeScript/JavaScript SDK
 *
 * @example
 * ```typescript
 * import { VeriBits } from 'veribits';
 *
 * const client = new VeriBits({ apiKey: 'your_api_key' });
 *
 * // Threat intelligence lookup
 * const result = await client.threatIntel.lookup('sha256_hash');
 *
 * // Scan file
 * const scan = await client.sandbox.submit('/path/to/file');
 * ```
 */

export interface VeriBitsConfig {
    apiKey: string;
    apiUrl?: string;
}

export interface ThreatLookupResult {
    hash: string;
    threat_score: number;
    is_malicious: boolean;
    confidence: number;
    results: Record<string, any>;
    timestamp: string;
}

export class VeriBits {
    private apiKey: string;
    private apiUrl: string;

    public threatIntel: ThreatIntelAPI;
    public sandbox: SandboxAPI;
    public cicd: CICDAPI;
    public crypto: CryptoAPI;

    constructor(config: VeriBitsConfig) {
        this.apiKey = config.apiKey;
        this.apiUrl = config.apiUrl || 'https://api.veribits.com';

        this.threatIntel = new ThreatIntelAPI(this);
        this.sandbox = new SandboxAPI(this);
        this.cicd = new CICDAPI(this);
        this.crypto = new CryptoAPI(this);
    }

    async request<T = any>(method: string, endpoint: string, data?: any): Promise<T> {
        const url = `${this.apiUrl}${endpoint}`;
        const response = await fetch(url, {
            method,
            headers: {
                'Authorization': `Bearer ${this.apiKey}`,
                'Content-Type': 'application/json',
                'User-Agent': 'veribits-typescript/1.0.0'
            },
            body: data ? JSON.stringify(data) : undefined
        });

        if (!response.ok) {
            throw new Error(`VeriBits API Error: ${response.status} ${response.statusText}`);
        }

        return response.json();
    }
}

export class ThreatIntelAPI {
    constructor(private client: VeriBits) {}

    async lookup(hash: string, options?: {
        sources?: string[];
        includeMetadata?: boolean;
    }): Promise<ThreatLookupResult> {
        return this.client.request('POST', '/api/v1/threat-intel/lookup', {
            hash,
            sources: options?.sources || ['virustotal', 'malwarebazaar'],
            include_metadata: options?.includeMetadata ?? true
        });
    }
}

export class SandboxAPI {
    constructor(private client: VeriBits) {}

    async submit(filePath: string, analysisType: 'static' | 'dynamic' | 'full' = 'full'): Promise<any> {
        // TODO: Implement file upload
        return this.client.request('POST', '/api/v1/sandbox/submit', {
            file_path: filePath,
            analysis_type: analysisType
        });
    }
}

export class CICDAPI {
    constructor(private client: VeriBits) {}

    async generateSBOM(format: 'cyclonedx' | 'spdx' = 'cyclonedx'): Promise<any> {
        return this.client.request('POST', '/api/v1/ci/sbom/generate', {
            format,
            directory: '.'
        });
    }
}

export class CryptoAPI {
    constructor(private client: VeriBits) {}

    async publishKey(keyData: string, keyType: 'pgp' | 'ssh' | 'x509' = 'pgp'): Promise<any> {
        return this.client.request('POST', '/api/v1/crypto/keys/publish', {
            key_data: keyData,
            key_type: keyType
        });
    }
}

export default VeriBits;
