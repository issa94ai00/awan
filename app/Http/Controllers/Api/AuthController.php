<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                // Unique because sign-in resolves an account by number: two
                // users sharing one would make the login answer depend on which
                // row happened to be created first.
                'phone' => 'nullable|string|max:20|unique:users,phone',
            ], [
                'name.required' => 'الاسم مطلوب',
                'email.required' => 'البريد الإلكتروني مطلوب',
                'email.email' => 'البريد الإلكتروني غير صحيح',
                'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
                'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
                'phone.max' => 'رقم الهاتف يجب ألا يتجاوز 20 خانة',
                'password.required' => 'كلمة المرور مطلوبة',
                'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
                'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'data' => null,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
            ]);

            $token = $user->createToken('api_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الحساب بنجاح',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الحساب',
                'data' => null
            ], 500);
        }
    }

    /**
     * Login user and create token
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required_without:phone|nullable|email',
                'phone' => 'required_without:email|nullable|string',
                'password' => 'required',
            ], [
                'email.required_without' => 'يجب إدخال البريد الإلكتروني أو رقم الهاتف',
                'email.email' => 'البريد الإلكتروني غير صحيح',
                'phone.required_without' => 'يجب إدخال البريد الإلكتروني أو رقم الهاتف',
                'password.required' => 'كلمة المرور مطلوبة',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'data' => null,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->filled('email')
                ? User::where('email', $request->email)->first()
                : User::where('phone', $request->phone)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                $identifier = $request->email ?: $request->phone;
                app(\App\Services\AuditService::class)->logFailedLogin($identifier, !$user ? 'user_not_found' : 'invalid_password', $user?->id);

                return response()->json([
                    'success' => false,
                    'message' => 'بيانات الدخول غير صحيحة',
                    'data' => null
                ], 401);
            }

            // Revoke only this device's previous token so other devices stay logged in
            $deviceName = (string) $request->header('User-Agent', 'unknown_device');
            $user->tokens()->where('name', $deviceName)->delete();

            $token = $user->createToken($deviceName)->plainTextToken;

            app(\App\Services\AuditService::class)->log(
                action: \App\Models\AuditLog::ACTION_LOGIN,
                entityType: \App\Models\User::class,
                entityId: $user->id,
                description: "تسجيل دخول ناجح للمستخدم: {$user->name}",
                module: \App\Models\AuditLog::MODULE_SECURITY,
                userId: $user->id,
                metadata: [
                    'device' => $deviceName,
                    'identifier' => $request->email ?: $request->phone,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الدخول',
                'data' => null
            ], 500);
        }
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if ($user) {
                app(\App\Services\AuditService::class)->log(
                    action: \App\Models\AuditLog::ACTION_LOGOUT,
                    entityType: \App\Models\User::class,
                    entityId: $user->id,
                    description: "تسجيل خروج للمستخدم: {$user->name}",
                    module: \App\Models\AuditLog::MODULE_SECURITY,
                    userId: $user->id
                );
            }

            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الخروج بنجاح',
                'data' => null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الخروج',
                'data' => null
            ], 500);
        }
    }

    /**
     * Get authenticated user details
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User data retrieved successfully',
            'data' => [
                'user' => new UserResource($request->user())
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
                // Ignores this user's own row, so saving the profile unchanged
                // does not report their own number as taken.
                'phone' => 'nullable|string|max:20|unique:users,phone,' . $user->id,
            ], [
                'name.required' => 'الاسم مطلوب',
                'email.required' => 'البريد الإلكتروني مطلوب',
                'email.email' => 'البريد الإلكتروني غير صحيح',
                'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
                'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'data' => null,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user->update($request->only(['name', 'email', 'phone']));

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الملف الشخصي بنجاح',
                'data' => [
                    'user' => new UserResource($user)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الملف الشخصي',
                'data' => null
            ], 500);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'password' => 'required|string|min:8|confirmed|different:current_password',
            ], [
                'current_password.required' => 'كلمة المرور الحالية مطلوبة',
                'password.required' => 'كلمة المرور الجديدة مطلوبة',
                'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
                'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
                'password.different' => 'كلمة المرور الجديدة يجب أن تختلف عن الحالية',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'data' => null,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // A wrong current password is a validation error, not an auth
            // failure: answering 401 made the client treat the session as
            // expired and log the user out over a typo.
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'كلمة المرور الحالية غير صحيحة',
                    'data' => null,
                    'errors' => ['current_password' => ['كلمة المرور الحالية غير صحيحة']],
                ], 422);
            }

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            // Sign out every other device; the one that made the change stays
            // signed in so the user isn't bounced to the login screen.
            $currentId = $this->currentTokenId($request);
            $user->tokens()
                ->when($currentId, fn ($query) => $query->where('id', '!=', $currentId))
                ->delete();

            app(\App\Services\AuditService::class)->logPasswordChange($user->id);

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير كلمة المرور بنجاح وتسجيل الخروج من الأجهزة الأخرى',
                'data' => null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير كلمة المرور',
                'data' => null
            ], 500);
        }
    }

    /**
     * List the devices (API tokens) the user is signed in on.
     */
    public function sessions(Request $request): JsonResponse
    {
        $currentId = $this->currentTokenId($request);

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'device' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'is_current' => $token->id === $currentId,
            ])
            ->sortByDesc('is_current')
            ->values();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => ['sessions' => $sessions],
        ]);
    }

    /**
     * Sign out one of the user's other devices.
     */
    public function revokeSession(Request $request, int $id): JsonResponse
    {
        if ($id === $this->currentTokenId($request)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إنهاء الجلسة الحالية من هنا، استخدم تسجيل الخروج',
                'data' => null,
            ], 422);
        }

        $deleted = $request->user()->tokens()->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'الجلسة غير موجودة',
                'data' => null,
            ], 404);
        }

        app(\App\Services\AuditService::class)->logRevokeSession(
            $request->user()->id,
            "تم إنهاء جلسة محددة (ID: {$id}) للمستخدم: {$request->user()->name}"
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج من الجهاز',
            'data' => null,
        ]);
    }

    /**
     * Sign out every device except the current one.
     */
    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $currentId = $this->currentTokenId($request);

        $count = $request->user()->tokens()
            ->when($currentId, fn ($query) => $query->where('id', '!=', $currentId))
            ->delete();

        app(\App\Services\AuditService::class)->logRevokeSession(
            $request->user()->id,
            "تم إنهاء كافة الجلسات الأخرى ({$count} جلسة) للمستخدم: {$request->user()->name}"
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج من الأجهزة الأخرى',
            'data' => ['revoked' => $count],
        ]);
    }

    /**
     * The bearer token behind this request, or null for cookie (SPA) auth.
     */
    private function currentTokenId(Request $request): ?int
    {
        $token = $request->user()->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token->id : null;
    }
}
