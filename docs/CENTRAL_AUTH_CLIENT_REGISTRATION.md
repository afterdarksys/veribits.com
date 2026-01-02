# VeriBits OAuth2 Client Registration Request

## Request for login.afterdarksys.com Administrator

Please register the following OAuth2 client for VeriBits integration with After Dark Systems Central Authentication.

---

## Client Details

| Field | Value |
|-------|-------|
| **Client Name** | VeriBits Security Platform |
| **Client ID** | `veribits-production` |
| **Application Type** | web |
| **Grant Types** | authorization_code, refresh_token |
| **Response Types** | code |
| **Token Endpoint Auth Method** | client_secret_post |

---

## Redirect URIs

### Production
- `https://veribits.com/api/v1/auth/central/callback`

### Development/Staging (optional)
- `https://staging.veribits.com/api/v1/auth/central/callback`
- `http://localhost:8080/api/v1/auth/central/callback`

---

## Scopes Requested

| Scope | Reason |
|-------|--------|
| `openid` | Required for OIDC authentication |
| `profile` | User name and basic profile info |
| `email` | User email address for account linking |
| `platforms` | Access to platform-specific permissions |

---

## Post-Logout Redirect URIs

- `https://veribits.com/`
- `https://veribits.com/login`

---

## Client Description

VeriBits is a security and verification tools platform that provides:
- DNS validation and propagation checking
- SSL/TLS certificate analysis
- Hash generation and lookup
- Security scanning (secrets, IAM policies, Docker, Terraform, Kubernetes)
- Code signing verification
- And 40+ other security tools

Integration with Central Auth allows:
1. Single Sign-On (SSO) for After Dark Systems users
2. Unified billing and subscription management
3. Cross-platform user identification
4. Enhanced security through centralized authentication

---

## Technical Contact

- **Email:** support@afterdarksys.com
- **Platform:** veribits.com

---

## Configuration After Registration

Once registered, please provide:
1. `client_id` (if different from requested)
2. `client_secret` (securely)

These values should be configured in VeriBits production environment:
- `OIDC_CLIENT_ID`
- `OIDC_CLIENT_SECRET`

---

## Files Ready for Integration

The following files have been created for Central Auth integration:

1. `app/src/Utils/OIDCClient.php` - OIDC client implementation
2. `app/src/Controllers/CentralAuthController.php` - Auth flow handlers
3. `db/migrations/024_central_auth.sql` - Database schema for Central auth linking
4. Updated `app/public/index.php` with Central auth routes:
   - `GET /api/v1/auth/central/status` - Check Central auth status
   - `GET /api/v1/auth/central/login` - Initiate OIDC flow
   - `GET /api/v1/auth/central/callback` - Handle OIDC callback
   - `GET /api/v1/auth/central/userinfo` - Get user info from Central
   - `POST /api/v1/auth/central/logout` - Logout from Central
   - `POST /api/v1/auth/central/link` - Link existing account to Central

---

## Verification Checklist

After client registration:

- [ ] Client ID and secret configured in .env.production
- [ ] Test authorization flow: `https://login.afterdarksys.com/oauth/authorize?client_id=veribits-production&response_type=code&redirect_uri=https://veribits.com/api/v1/auth/central/callback&scope=openid profile email platforms`
- [ ] Test token exchange
- [ ] Test userinfo endpoint
- [ ] Test account linking
- [ ] Test logout flow
