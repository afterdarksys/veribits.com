import {
    ICredentialType,
    INodeProperties,
} from 'n8n-workflow';

export class VeriBitsApi implements ICredentialType {
    name = 'veribitsApi';
    displayName = 'VeriBits API';
    documentationUrl = 'https://veribits.com/docs/api';

    properties: INodeProperties[] = [
        {
            displayName: 'API Key',
            name: 'apiKey',
            type: 'string',
            typeOptions: {
                password: true,
            },
            default: '',
            required: true,
            description: 'Your VeriBits API key. Get one at https://veribits.com/dashboard',
        },
        {
            displayName: 'Base URL',
            name: 'baseUrl',
            type: 'string',
            default: 'https://veribits.com/api/v1',
            description: 'The base URL of the VeriBits API',
        },
    ];

    // Test the credentials
    async authenticate(credentials: { apiKey: string; baseUrl: string }): Promise<void> {
        const response = await fetch(`${credentials.baseUrl}/health`, {
            headers: {
                'X-API-Key': credentials.apiKey,
            },
        });

        if (!response.ok) {
            throw new Error('Invalid API key or server unreachable');
        }
    }
}
