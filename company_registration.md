# API: تسجيل شركة

Endpoint: `POST /api/companies`

ملخص: تسجيل بيانات شركة جديدة في النظام.

Request JSON:

{
  "name": "اسم الشركة",            // string, مطلوب
  "contact": "رقم هاتف أو إيميل", // string, مطلوب
  "address": "نص العنوان",         // string, مطلوب
  "latitude": 24.7136,             // number, اختياري
  "longitude": 46.6753             // number, اختياري
}

Validation rules (Laravel example):

```php
$request->validate([
  'name' => 'required|string|max:255',
  'contact' => 'required|string|max:255',
  'address' => 'required|string',
  'latitude' => 'nullable|numeric',
  'longitude' => 'nullable|numeric',
]);
```

Response (success):

HTTP 201
{
  "data": {
    "id": 123,
    "name": "...",
    "contact": "...",
    "address": "...",
    "latitude": 24.7,
    "longitude": 46.6,
    "created_at": "..."
  }
}

Errors: return appropriate 4xx with validation errors in standard Laravel format.

Auth: Prefer Bearer token `Authorization: Bearer {token}`. If open endpoint is desired, indicate so.

Notes for backend team:
- Persist company record in `companies` table.
- Add unique constraints if required (e.g., tax id or phone).
- Return created resource with HTTP 201.
- Consider soft-deletes and audit fields (`created_by`, `updated_by`).

Optional: support multipart upload if later you want to attach logo or documents.

Client map integration:
- The mobile client will use Google Maps to capture `latitude` and `longitude` (tap to pick or "use current location").
- Mobile apps must configure Google Maps API keys on each platform (Android: `AndroidManifest.xml`, iOS: AppDelegate/Info.plist) and enable Maps SDK + Maps SDK for Android/iOS in Google Cloud Console.
- If you prefer the server to reverse-geocode the address, accept `latitude`/`longitude` and return the resolved address in the created resource.

App integration notes:
- The app uses a shared `ApiService` and sends the request to `https://awaanaltakadom.sy/api/companies`.
- The company registration page is reachable at app route `/home/company-registration` after login.
- The request body is JSON, and the client sends `Content-Type: application/json`.
- If the user is authenticated, the app attaches `Authorization: Bearer {token}` automatically.
- The current client implementation does not upload files for registration; logo/document upload should be added later as multipart only if needed.

