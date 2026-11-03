# SCIM 2.0 User Attribute Mapping for ULAMS

## Attribute Mapping Table

| SCIM Attribute       | ULAMS User Field | Notes          |
|----------------------|------------------|----------------|
| `userName`           | TODO(verify)     |                |
| `name.givenName`     | TODO(verify)     |                |
| `name.familyName`    | TODO(verify)     |                |
| `emails[primary]`    | TODO(verify)     |                |
| `active`             | TODO(verify)     |                |
| `externalId`         | TODO(verify)     |                |
| `groups`             | TODO(verify)     |                |

## Supported Operations

- Create
- Read
- Update
- Delete
- Search

## Error Responses

- `400 Bad Request`: Invalid request payload.
- `401 Unauthorized`: Authentication failed.
- `403 Forbidden`: Insufficient permissions.
- `404 Not Found`: Resource not found.
- `500 Internal Server Error`: Server error.
