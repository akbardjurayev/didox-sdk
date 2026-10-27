# Didox API map

This map is based on the official Didox integrator documentation available at
<https://api-docs.didox.uz/ru/>. No official downloadable OpenAPI/Swagger file
was located during research. Endpoints below are documented by Didox; request
schemas that vary by document type remain intentionally generic.

## Environment and authentication

| Item | Confirmed value |
|---|---|
| Test base URL | `https://testapi3.didox.uz/` |
| Production base URL | `https://api-partners.didox.uz/` |
| Partner header | `Partner-Authorization: <PARTNER_TOKEN>` |
| User header | `user-key: <USER_TOKEN>` |
| Locale | `ru` or `uz` |
| User token lifetime | 360 minutes; UUID format |
| JSON content type | `application/json` |

## Confirmed endpoints

| Feature | HTTP method | Endpoint | Request / response notes | Auth |
|---|---:|---|---|---|
| User login by E-IMZO | POST | `/v1/auth/{taxId}/token/{locale}` | JSON `signature`; returns user token | Partner |
| User login by password | POST | `/v1/auth/{taxId}/password/{locale}` | JSON `password`; returns user token | Partner |
| Company login | POST | `/v1/auth/company/{companyTaxId}/login/{locale}` | Optional `branchNum`; returns token and permissions | Partner + user |
| Timestamp signature | POST | `/v1/dsvs/timestamp` | JSON `pkcs7`, `signatureHex`; returns `timeStampTokenB64` | Partner |
| User registration | POST | `/v1/auth/signup` | JSON signature plus registration fields | Partner |
| Join signatures | POST | `/v1/dsvs/signature/join` | JSON `signature1`, `signature2`; used for incoming signing | Partner + user |
| List documents | GET | `/v2/documents` | Pagination and filters; JSON response | Partner + user |
| Document statistics | GET | `/v2/documents/statistics/all` | Status-count object | Partner + user |
| Document details | GET | `/v1/documents/{id}` | `data.json`, `data.document`, `data.toSign`, related documents | Partner + user |
| Document privileges | GET | `/v1/documents/{id}/privileges/{locale}` | JSON privilege list | Partner + user |
| Create draft | POST | `/v1/documents/{docType}/create/{locale}` | Type-specific JSON; arbitrary documents require PDF data | Partner + user |
| Update draft | POST | `/v1/documents/{id}/update/{docType}/{locale}` | Type-specific JSON; arbitrary documents may include PDF data | Partner + user |
| Delete draft | POST | `/v1/documents/{id}/delete/draft` | Returns `{data:true}` | Partner + user |
| Signing payload | POST | `/v1/documents/{id}/tosign` | JSON `action`, optional `comment` | Partner + user |
| Sign outgoing | POST | `/v1/documents/{id}/sign` | JSON `signature`; returns success and optional warning details | Partner + user |
| Accept incoming | POST | `/v1/documents/{id}/sign` | Joined signature for incoming workflow | Partner + user |
| Reject document | POST | `/v1/documents/{id}/reject` | JSON `signature`, `comment` | Partner + user |
| Cancel document | POST | `/v1/documents/{id}/delete` | JSON `signature` | Partner + user |
| Confirm TTN delivery | POST | `/v1/documents/{id}/give` | JSON signature | Partner + user |
| Return TTN before delivery | POST | `/v1/documents/{id}/tillreturn` | JSON signature and comment | Partner + user |
| Return delivered TTN | POST | `/v1/documents/{id}/return` | JSON signature and comment | Partner + user |
| HTML print form | GET | `/v1/documents/view/{id}/html/{locale}` | Raw HTML | Partner + user |
| HTML print form | GET | `/v1/documents/{id}/html/{locale}` | Raw HTML | Partner |
| PDF print form | GET | `/v1/documents/view/{id}/pdf/{locale}` | Binary PDF | Partner + user |
| PDF print form | GET | `/v1/documents/{id}/pdf/{locale}` | Binary PDF | Partner |
| PDF as base64 | GET | `/v1/documents/{id}/file/true/{locale}` | JSON `file.data` | Partner |
| Document archive | GET | `/v1/documents/{id}/archive` | Binary ZIP | Partner + user |
| Contract data for invoice | GET | `/v1/documents/contract/{contractId}/info/{locale}` | Contract JSON | Partner + user |
| Exchange lot | GET | `/v1/documents/exchange` | Lot JSON | Partner + user |
| Exchange deal types | GET | `/v1/documents/exchange/types` | Deal-type list | Partner + user |
| Exchange lot type prefix | GET | `/v1/documents/exchange/lotswithtypes/{locale}` | Query `buyerTin`, `lotId` | Partner + user |
| Current profile | GET | `/v1/profile` | Optional `isSeller` query | Partner + user |
| Update profile | POST | `/v1/profile/update` | Partial profile JSON | Partner + user |
| Profile operators | GET | `/v1/profile/operators` | Operator map | Partner + user |
| Profile branches | GET | `/v1/profile/branches` | Branch list | Partner + user |
| Product classes | GET | `/v1/profile/productClassCodes` | Paginated/searchable list | Partner + user |
| Attach product class | POST | `/v1/profile/productClasses` | JSON `classCode` | Partner + user |
| Detach product class | DELETE | `/v1/profile/productClasses/{classCode}` | Success object | Partner + user |
| VAT status | GET | `/v1/profile/vatRegStatus/{taxId}` | Optional date and seller query | Partner + user |
| Product packages | GET | `/v1/profile/{taxId}/productClasses/check/{code}/{lang}` | Package list | Partner + user |
| Warehouses | GET | `/v1/profile/warehouses/{taxId}` | Warehouse list | Partner + user |
| Taxpayer type | GET | `/v1/profile/taxpayerType/{taxId}/{lang}` | Optional date query | Partner + user |
| Company user roles | PUT | `/v1/profile/company/users` | Signed permission payload | Partner + user |
| Banks | GET | `/v1/banks/all` | Localized catalog | Partner + user |
| Measures | GET | `/v1/measures/all` | Localized catalog | Partner + user |
| Regions | GET | `/v1/regions/all` | Localized catalog | Partner + user |
| Districts | GET | `/v1/districts/all` | Localized catalog | Partner + user |
| Railway lines | GET | `/v1/utils/waybills/railwaylines` | Localized catalog | Partner + user |
| Contract templates | GET | `/v1/document-template` | `size`, `page`, `docType` queries | Partner + user |
| Contract template | GET | `/v1/document-template/{id}` | Template details | Partner + user |
| Create template | POST | `/v1/document-template` | Template JSON | Partner + user |
| Update template | PUT | `/v1/document-template/{id}` | Template JSON | Partner + user |
| Delete template | DELETE | `/v1/document-template/{id}` | Empty object response | Partner + user |

## Implementation status

| Feature | Status | Audit note |
|---|---|---|
| Password and E-IMZO login | **CONFIRMED** | Routes, headers, and documented request fields match the authentication documentation. |
| Company login | **CONFIRMED** | `branchNum` is optional and the user token is sent as `user-key`. |
| Timestamp attachment and registration | **CONFIRMED** | Implemented with the documented `pkcs7`, `signatureHex`, and registration payload boundary. |
| Signature joining | **CONFIRMED** | Implemented as the documented low-level join operation. |
| Token expiration | **CONFIRMED** | Didox documents 360-minute tokens; the SDK records a 360-minute local expiry for tokens it receives. |
| Token refresh / revocation | **NOT IMPLEMENTED** | No refresh or revocation operation is documented on the official authentication page. Local logout only clears the configured token store. |
| Document list, statistics, details, privileges | **CONFIRMED** | Routes and documented query/response boundaries match the document page. |
| Draft create, update, delete | **CONFIRMED** | Type-specific payloads remain arrays because schemas vary by document type. |
| Outgoing signing, rejection, cancellation | **CONFIRMED** | Signature payloads and action routes are documented. |
| Complete incoming signing workflow | **UNVERIFIED** | The SDK exposes `joinSignatures()` and generic `sign()`, but does not implement the incompletely documented `documentBase64` step. |
| TTN actions | **CONFIRMED** | `give`, `tillreturn`, and `return` are documented. |
| HTML, PDF, PDF-base64, archive | **CONFIRMED** | Response bodies are handled as HTML, binary, JSON, or ZIP as documented. |
| Arbitrary-document PDF upload | **NOT IMPLEMENTED** | Didox mentions base64/file input but does not define one definitive transport encoding. |
| Contract/exchange lookup operations | **CONFIRMED** | Routes and query fields are documented. |
| Profile operations | **CONFIRMED** | Implemented routes match the profile page. The SDK requires the explicitly documented warehouse `{taxId}` path and does not call the ambiguous no-argument variant. |
| Catalog operations | **CONFIRMED** | Banks, measures, regions, districts, and railway-line routes are documented. |
| Document template CRUD | **CONFIRMED** | Routes and methods match the template page. |
| Utility endpoints other than railway lines | **NOT IMPLEMENTED** | The official utilities page documents many additional lookup and transport operations; they are outside the current SDK service surface. |
| E-IMZO implementation inside SDK | **NOT IMPLEMENTED** | E-IMZO is intentionally represented by an application-provided signer contract. |
| HTTP 404 mapping | **UNVERIFIED** | The SDK maps 404 to `NotFoundException`, but Didox examples frequently report missing documents as 422. |

## Document types confirmed by the document creation endpoint

`002` invoice without act; `008` pharmaceutical invoice; `023` hybrid invoice;
`041` waybill; `005` completed-work act; `006` power of attorney; `062` new
power of attorney; `007` state-tax contract; `000` arbitrary document; `010`
multi-party arbitrary document; `052` reconciliation act; `054` acceptance-transfer
act; `075` founders' meeting protocol; `031` tax-authority letter.

## Confirmed error behavior

The documentation shows `401`, `403`, `422`, `423`, `429`, `500`, and `503`
responses depending on the endpoint. Bodies are not uniform: they may be a
field-validation map, `{data:{status,message,context}}`, `{success:false,error}`,
an `errorDetails` object, a plain string, or a binary/error response.

## Unknown or unverified

- No official machine-readable OpenAPI/Swagger document was located.
- Full JSON schemas differ by document type and are not normalized here.
- The exact transport encoding for arbitrary-document PDF upload is unclear
  (the docs mention base64/file but do not establish a single multipart contract).
- The `/v1/documents/{id}/documentBase64` endpoint is referenced by the incoming
  signing procedure but is not fully described in the endpoint index.
- Global rate limits, retry policy, refresh/revocation behavior, and complete
  404 semantics are not specified consistently.
- Status codes are document-type-specific; only documented values should become
  enums.
- The document overview table omits `{locale}` from the update route while the
  detailed operation documents `/v1/documents/{id}/update/{docType}/{locale}`;
  the SDK follows the detailed operation page and records the discrepancy here.

Sources:

- <https://api-docs.didox.uz/ru/home>
- <https://api-docs.didox.uz/ru/integrators-registration>
- <https://api-docs.didox.uz/ru/integrators-documents>
- <https://api-docs.didox.uz/ru/integrators-profile>
- <https://api-docs.didox.uz/ru/integrators-catalogs>
- <https://api-docs.didox.uz/ru/integrators-document-template>
- <https://api-docs.didox.uz/ru/integrators-utils>
