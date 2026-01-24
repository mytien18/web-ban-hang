<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    /** Đăng ký */
    public function register(Request $r)
    {
        try {
            $data = $r->validate([
                'name'     => ['required', 'string', 'max:120'],
                'email'    => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('user','email')->where(fn($q)=>$q->whereNull('deleted_at')),
                ],
                'password' => ['required', 'string', 'min:6'],
                'phone'    => ['nullable', 'string', 'max:20'],
                'address'  => ['nullable', 'string', 'max:500'],
                'birthday' => ['nullable', 'date'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();
            return response()->json([
                'message' => $firstError,
                'errors' => $errors
            ], 422);
        }

        $username = explode('@', $data['email'])[0];

        try {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'username' => $username,
                'password' => Hash::make($data['password']),
                'roles'    => 'customer',
                'status'   => 1,
            ]);

            $customer = Customer::create([
                'user_id'  => $user->id,
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'address'  => $data['address'] ?? null,
                'birthday' => $data['birthday'] ?? null,
            ]);

            // Gửi link xác thực (email)
            try {
                $this->sendVerifyLink($user);
            } catch (\Throwable $e) {
                \Log::error('Failed to send verification email: ' . $e->getMessage());
            }

            return response()->json([
                'message'  => 'Đăng ký thành công! Vui lòng kiểm tra email để xác thực tài khoản.',
                'user'     => $user,
                'customer' => $customer,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }

    /** Gửi LINK xác thực qua EmailJS nếu cấu hình, fallback dùng Blade + Mail */
    private function sendVerifyLink(User $user): void
    {
        $token = Str::random(40);
        cache()->put("verify_{$user->id}", $token, now()->addHours(24));
        $verifyUrl = url("/api/v1/auth/verify?uid={$user->id}&token={$token}");

        $emailjsService  = env('EMAILJS_SERVICE_ID');
        $emailjsTemplate = env('EMAILJS_TEMPLATE_ID');
        $emailjsUser     = env('EMAILJS_PUBLIC_KEY');

        // Debug: Log config status
        \Log::info('EmailJS Config Check:', [
            'service_id' => $emailjsService ? 'SET' : 'NOT SET',
            'template_id' => $emailjsTemplate ? 'SET' : 'NOT SET',
            'user_id' => $emailjsUser ? 'SET' : 'NOT SET',
            'to_email' => $user->email,
        ]);

        if ($emailjsService && $emailjsTemplate && $emailjsUser) {
            // Sử dụng EmailJS với template "Welcome"
            $params = [
                'to_email'     => $user->email,
                'to_name'      => $user->name ?? 'bạn',
                'verify_url'   => $verifyUrl,
                'app_name'     => config('app.name', 'Dola Bakery'),
                'expire_hours' => 24,
            ];
            
            \Log::info('Sending EmailJS verification email:', [
                'to' => $user->email,
                'template_id' => $emailjsTemplate,
                'verify_url' => $verifyUrl,
            ]);

            try {
                $resp = Http::asJson()->post('https://api.emailjs.com/api/v1.0/email/send', [
                    'service_id'      => $emailjsService,
                    'template_id'     => $emailjsTemplate,
                    'user_id'         => $emailjsUser,
                    'template_params' => $params,
                ]);
                
                $statusCode = $resp->status();
                $responseBody = $resp->body();
                
                if ($resp->ok()) {
                    \Log::info('EmailJS: Verification email sent successfully to ' . $user->email);
                } else {
                    \Log::warning('EmailJS send (verify link) failed:', [
                        'status' => $statusCode,
                        'response' => $responseBody,
                        'email' => $user->email,
                    ]);
                    // Fallback to Laravel Mail if EmailJS fails
                    try {
                        Mail::send('emails.verify', compact('user', 'verifyUrl'), function($m) use($user){
                            $m->to($user->email)->subject('Xác thực tài khoản - Dola Bakery');
                        });
                        \Log::info('Laravel Mail fallback sent successfully to ' . $user->email);
                    } catch (\Throwable $mailError) {
                        \Log::error('Laravel Mail fallback failed: ' . $mailError->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('EmailJS error (verify link):', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'email' => $user->email,
                ]);
                // Fallback to Laravel Mail on error
                try {
                    Mail::send('emails.verify', compact('user', 'verifyUrl'), function($m) use($user){
                        $m->to($user->email)->subject('Xác thực tài khoản - Dola Bakery');
                    });
                    \Log::info('Laravel Mail fallback sent successfully to ' . $user->email);
                } catch (\Throwable $mailError) {
                    \Log::error('Laravel Mail fallback also failed:', [
                        'message' => $mailError->getMessage(),
                        'email' => $user->email,
                    ]);
                }
            }
        } else {
            // Fallback: Sử dụng Laravel Mail nếu EmailJS chưa được cấu hình
            \Log::warning('EmailJS not configured, using Laravel Mail fallback', [
                'email' => $user->email,
                'missing' => [
                    'service_id' => !$emailjsService,
                    'template_id' => !$emailjsTemplate,
                    'user_id' => !$emailjsUser,
                ],
            ]);
            try {
                Mail::send('emails.verify', compact('user', 'verifyUrl'), function($m) use($user){
                    $m->to($user->email)->subject('Xác thực tài khoản - Dola Bakery');
                });
                \Log::info('Laravel Mail sent successfully to ' . $user->email);
            } catch (\Throwable $mailError) {
                \Log::error('Laravel Mail failed:', [
                    'message' => $mailError->getMessage(),
                    'email' => $user->email,
                ]);
            }
        }
    }

    /** Gửi OTP cho người dùng qua email, lưu cache 10 phút */
    private function sendOtpForUser(User $user): void
    {
        $otp = (string) random_int(100000, 999999);
        cache()->put("otp_{$user->id}", $otp, now()->addMinutes(10));
        cache()->put("otp_attempts_{$user->id}", 0, now()->addMinutes(10));

        // If EmailJS is configured, send via EmailJS API; otherwise use Blade mailer
        $emailjsService = env('EMAILJS_SERVICE_ID');
        $emailjsTemplate = env('EMAILJS_TEMPLATE_ID');
        $emailjsUser = env('EMAILJS_PUBLIC_KEY'); // aka user_id

        if ($emailjsService && $emailjsTemplate && $emailjsUser) {
            // Compose template params expected by your EmailJS template
            $params = [
                'to_email'   => $user->email,
                'to_name'    => $user->name ?? 'bạn',
                'otp'        => $otp,
                'app_name'   => config('app.name', 'Dola Bakery'),
                'expire_min' => 10,
            ];

            try {
                $resp = Http::asJson()->post('https://api.emailjs.com/api/v1.0/email/send', [
                    'service_id'     => $emailjsService,
                    'template_id'    => $emailjsTemplate,
                    'user_id'        => $emailjsUser,
                    'template_params'=> $params,
                ]);
                if (!$resp->ok()) {
                    \Log::warning('EmailJS send failed: ' . $resp->status() . ' ' . $resp->body());
                }
            } catch (\Throwable $e) {
                \Log::error('EmailJS error: ' . $e->getMessage());
            }
        } else {
            Mail::send('emails.verify_otp', ['user' => $user, 'otp' => $otp], function ($m) use ($user) {
                $m->to($user->email)->subject('Mã xác thực OTP - Dola Bakery');
            });
        }
    }

    /** Xác thực email */
    public function verifyEmail(Request $r)
    {
        $uid   = (int) $r->query('uid');
        $token = (string) $r->query('token');
        
        try {
            $user = User::findOrFail($uid);
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url', 'http://localhost:3000') . '/verify?error=notfound');
        }

        if (!$token || cache()->get("verify_{$uid}") !== $token) {
            return redirect(config('app.frontend_url', 'http://localhost:3000') . '/verify?error=invalid');
        }

        try {
            $user->email_verified_at = now();
            $user->save();
            cache()->forget("verify_{$uid}");
            
            return redirect(config('app.frontend_url', 'http://localhost:3000') . '/verify?success=1');
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url', 'http://localhost:3000') . '/verify?error=system');
        }
    }

    /** Test EmailJS - Debug endpoint */
    public function testEmailJS(Request $r)
    {
        $emailjsService  = env('EMAILJS_SERVICE_ID');
        $emailjsTemplate = env('EMAILJS_TEMPLATE_ID');
        $emailjsUser     = env('EMAILJS_PUBLIC_KEY');
        
        $testEmail = $r->query('email', 'test@example.com');
        
        $config = [
            'service_id_set' => !empty($emailjsService),
            'template_id_set' => !empty($emailjsTemplate),
            'user_id_set' => !empty($emailjsUser),
            'service_id' => $emailjsService ? substr($emailjsService, 0, 10) . '...' : 'NOT SET',
            'template_id' => $emailjsTemplate ?? 'NOT SET',
            'user_id' => $emailjsUser ? substr($emailjsUser, 0, 10) . '...' : 'NOT SET',
        ];
        
        if (!$emailjsService || !$emailjsTemplate || !$emailjsUser) {
            return response()->json([
                'success' => false,
                'error' => 'EmailJS chưa được cấu hình đầy đủ',
                'config' => $config,
                'message' => 'Vui lòng kiểm tra file .env và đảm bảo có đủ 3 biến: EMAILJS_SERVICE_ID, EMAILJS_TEMPLATE_ID, EMAILJS_PUBLIC_KEY',
            ]);
        }
        
        try {
            $verifyUrl = url("/api/v1/auth/verify?uid=1&token=test123");
            $params = [
                'to_email'     => $testEmail,
                'to_name'      => 'Test User',
                'verify_url'   => $verifyUrl,
                'app_name'     => config('app.name', 'Dola Bakery'),
                'expire_hours' => 24,
            ];
            
            \Log::info('Testing EmailJS with params:', $params);
            
            $resp = Http::asJson()->post('https://api.emailjs.com/api/v1.0/email/send', [
                'service_id'      => $emailjsService,
                'template_id'     => $emailjsTemplate,
                'user_id'         => $emailjsUser,
                'template_params' => $params,
            ]);
            
            $statusCode = $resp->status();
            $responseBody = $resp->body();
            $isOk = $resp->ok();
            
            \Log::info('EmailJS test response:', [
                'status' => $statusCode,
                'ok' => $isOk,
                'body' => $responseBody,
            ]);
            
            return response()->json([
                'success' => $isOk,
                'status_code' => $statusCode,
                'response' => $responseBody,
                'config' => $config,
                'test_email' => $testEmail,
                'message' => $isOk 
                    ? 'Email đã được gửi thành công! Kiểm tra email: ' . $testEmail
                    : 'Lỗi: ' . $responseBody,
            ]);
        } catch (\Throwable $e) {
            \Log::error('EmailJS test error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'config' => $config,
                'test_email' => $testEmail,
            ], 500);
        }
    }

    /** Gửi lại mail xác thực */
    public function resendVerify(Request $r)
    {
        $r->validate(['email'=>'required|email']);
        $user = User::where('email', $r->email)->first();
        if (!$user) return response()->json(['message'=>'Không tìm thấy tài khoản'], 404);
        
        if ($user->email_verified_at) {
            return response()->json(['message'=>'Email đã được xác thực rồi'], 400);
        }

        try {
            $this->sendVerifyLink($user);
            
            return response()->json(['message'=>'Đã gửi lại email xác thực. Vui lòng kiểm tra hộp thư của bạn.']);
        } catch (\Throwable $e) {
            \Log::error('Failed to resend verification email: ' . $e->getMessage());
            return response()->json(['message'=>'Không thể gửi email. Vui lòng thử lại sau.'], 500);
        }
    }

    /** Xác thực email bằng OTP */
    public function verifyOtp(Request $r)
    {
        $data = $r->validate([
            'email' => ['required','email'],
            'otp'   => ['required','digits:6'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy tài khoản'], 404);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email đã được xác thực'], 400);
        }

        $key = "otp_{$user->id}";
        $attemptKey = "otp_attempts_{$user->id}";
        $cachedOtp = cache()->get($key);

        if (!$cachedOtp) {
            return response()->json(['message' => 'OTP đã hết hạn. Vui lòng yêu cầu gửi lại.'], 400);
        }

        // Giới hạn số lần nhập sai
        $attempts = (int) cache()->get($attemptKey, 0);
        if ($attempts >= 5) {
            return response()->json(['message' => 'Bạn đã nhập sai quá số lần cho phép. Vui lòng yêu cầu OTP mới.'], 429);
        }

        if ($data['otp'] !== $cachedOtp) {
            cache()->put($attemptKey, $attempts + 1, now()->addMinutes(10));
            return response()->json(['message' => 'OTP không đúng. Vui lòng kiểm tra lại.'], 400);
        }

        // Thành công
        $user->email_verified_at = now();
        $user->save();
        cache()->forget($key);
        cache()->forget($attemptKey);

        return response()->json(['message' => 'Xác thực email thành công']);
    }

    /** Gửi lại OTP (rate limit 60s) */
    public function resendOtp(Request $r)
    {
        $data = $r->validate([
            'email' => ['required','email'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) return response()->json(['message' => 'Không tìm thấy tài khoản'], 404);
        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email đã được xác thực'], 400);
        }

        $limiterKey = 'resend_otp_' . $user->id;
        if (cache()->has($limiterKey)) {
            return response()->json(['message' => 'Vui lòng chờ trước khi gửi lại OTP.'], 429);
        }

        try {
            $this->sendOtpForUser($user);
            cache()->put($limiterKey, 1, now()->addSeconds(60));
            return response()->json(['message' => 'Đã gửi OTP. Vui lòng kiểm tra hộp thư.']);
        } catch (\Throwable $e) {
            \Log::error('Failed to resend OTP: ' . $e->getMessage());
            return response()->json(['message' => 'Không thể gửi OTP. Vui lòng thử lại sau.'], 500);
        }
    }

    /** Đăng nhập: identifier = email | phone | username */
    public function login(Request $r)
    {
        // Validate input
        try {
            $data = $r->validate([
                'identifier' => ['required', 'string'],
                'password'   => ['required', 'string', 'min:6'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();
            return response()->json([
                'message' => $firstError,
                'errors' => $errors
            ], 422);
        }

        $id = $data['identifier'];
        $user = User::where('email',$id)
            ->orWhere('phone',$id)
            ->orWhere('username',$id)
            ->first();

        // Kiểm tra tài khoản tồn tại
        if (!$user) {
            // Kiểm tra xem có phải là email không
            if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'message' => 'Không tìm thấy tài khoản với email này. Vui lòng kiểm tra lại hoặc đăng ký tài khoản mới.',
                    'type' => 'account_not_found'
                ], 404);
            } else {
                return response()->json([
                    'message' => 'Không tìm thấy tài khoản với thông tin đăng nhập này.',
                    'type' => 'account_not_found'
                ], 404);
            }
        }

        // Kiểm tra mật khẩu
        if (!Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Mật khẩu không đúng. Vui lòng kiểm tra lại.',
                'type' => 'wrong_password'
            ], 401);
        }

        // Kiểm tra trạng thái tài khoản
        if ((int)$user->status !== 1) {
            return response()->json([
                'message' => 'Tài khoản của bạn đang bị khoá. Vui lòng liên hệ admin để được hỗ trợ.',
                'type' => 'account_locked'
            ], 403);
        }

        $needs_verify = is_null($user->email_verified_at);

        $token = $user->createToken('auth', ['customer'])->plainTextToken;

        return response()->json([
            'message'       => 'Đăng nhập thành công!',
            'token'         => $token,
            'user'          => $user,
            'needs_verify'  => $needs_verify,
        ]);
    }

    /** Thông tin người dùng hiện tại */
    public function me(Request $r)
    {
        try {
            $user = $r->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            $customer = Customer::where('user_id', $user->id)->first();
            return response()->json(compact('user', 'customer'));
        } catch (\Throwable $e) {
            \Log::error('AuthController::me error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $r->user()?->id,
            ]);
            return response()->json([
                'message' => 'Lỗi khi lấy thông tin người dùng: ' . $e->getMessage()
            ], 500);
        }
    }

    /** Đăng xuất */
    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return response()->json(['message'=>'Đã đăng xuất']);
    }

    /** Cập nhật hồ sơ */
    public function updateProfile(Request $r)
    {
        $user = $r->user();
        $data = $r->validate([
            'name'     => 'required|string|max:120',
            'email'    => [
                'required','email','max:255',
                Rule::unique('user','email')
                    ->ignore($user->id)
                    ->where(fn($q)=>$q->whereNull('deleted_at')), // bỏ qua record đã xóa mềm
            ],
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string|max:255',
            'birthday' => 'nullable|date',
            'avatar'   => 'nullable|string|max:255',
        ]);

        $user->update([
            'name'   => $data['name'],
            'email'  => $data['email'],
            'phone'  => $data['phone'] ?? null,
            'avatar' => $data['avatar'] ?? null,
        ]);

        $customer = Customer::where('user_id',$user->id)->first();
        if ($customer) {
            $customer->update([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'address'  => $data['address'] ?? null,
                'birthday' => $data['birthday'] ?? null,
            ]);
        }

        return response()->json(['message'=>'Cập nhật thành công','user'=>$user,'customer'=>$customer]);
    }

    /** Đổi mật khẩu */
    public function changePassword(Request $r)
    {
        $r->validate([
            'current_password' => 'required|string|min:6',
            'new_password'     => 'required|string|min:6|different:current_password',
        ]);

        $user = $r->user();

        if (!Hash::check($r->current_password, $user->password)) {
            return response()->json(['message' => 'Mật khẩu hiện tại không đúng'], 400);
        }

        // Hash mật khẩu mới
        $user->password = Hash::make($r->new_password);
        $user->save();

        // (Tuỳ chọn) đăng xuất các token khác:
        // $user->tokens()->where('id','!=',$r->user()->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    }

    /** Gửi OTP cho reset password */
    private function sendOtpForPasswordReset(User $user): void
    {
        $otp = (string) random_int(100000, 999999);
        cache()->put("reset_otp_{$user->id}", $otp, now()->addMinutes(10));
        cache()->put("reset_otp_attempts_{$user->id}", 0, now()->addMinutes(10));

        // If EmailJS is configured, send via EmailJS API; otherwise use Blade mailer
        $emailjsService = env('EMAILJS_SERVICE_ID');
        $emailjsTemplate = env('EMAILJS_TEMPLATE_ID');
        $emailjsUser = env('EMAILJS_PUBLIC_KEY');

        if ($emailjsService && $emailjsTemplate && $emailjsUser) {
            // Compose template params expected by your EmailJS template
            $params = [
                'to_email'   => $user->email,
                'to_name'    => $user->name ?? 'bạn',
                'otp'        => $otp,
                'otp_code'   => $otp, // alias for compatibility
                'app_name'   => config('app.name', 'Dola Bakery'),
                'expire_min' => 10,
            ];

            try {
                $resp = Http::asJson()->post('https://api.emailjs.com/api/v1.0/email/send', [
                    'service_id'     => $emailjsService,
                    'template_id'    => $emailjsTemplate,
                    'user_id'        => $emailjsUser,
                    'template_params'=> $params,
                ]);
                if (!$resp->ok()) {
                    \Log::warning('EmailJS send (reset password OTP) failed: ' . $resp->status() . ' ' . $resp->body());
                    // Fallback to Laravel Mail
                    Mail::send('emails.reset_password_otp', ['user' => $user, 'otp' => $otp], function ($m) use ($user) {
                        $m->to($user->email)->subject('Mã OTP đặt lại mật khẩu - Dola Bakery');
                    });
                } else {
                    \Log::info('EmailJS: Reset password OTP sent successfully to ' . $user->email);
                }
            } catch (\Throwable $e) {
                \Log::error('EmailJS error (reset password OTP): ' . $e->getMessage());
                // Fallback to Laravel Mail
                try {
                    Mail::send('emails.reset_password_otp', ['user' => $user, 'otp' => $otp], function ($m) use ($user) {
                        $m->to($user->email)->subject('Mã OTP đặt lại mật khẩu - Dola Bakery');
                    });
                } catch (\Throwable $mailError) {
                    \Log::error('Laravel Mail fallback also failed: ' . $mailError->getMessage());
                }
            }
        } else {
            // Fallback: Sử dụng Laravel Mail nếu EmailJS chưa được cấu hình
            Mail::send('emails.reset_password_otp', ['user' => $user, 'otp' => $otp], function ($m) use ($user) {
                $m->to($user->email)->subject('Mã OTP đặt lại mật khẩu - Dola Bakery');
            });
        }
    }

    /** Gửi OTP để reset password (public) */
    public function forgotPassword(Request $r)
    {
        $data = $r->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy tài khoản với email này'], 404);
        }

        // Kiểm tra rate limit
        $limiterKey = 'forgot_password_' . $user->id;
        if (cache()->has($limiterKey)) {
            return response()->json(['message' => 'Vui lòng chờ 60 giây trước khi yêu cầu lại.'], 429);
        }

        try {
            $this->sendOtpForPasswordReset($user);
            cache()->put($limiterKey, 1, now()->addSeconds(60));
            return response()->json(['message' => 'Đã gửi mã OTP đến email của bạn. Vui lòng kiểm tra hộp thư.']);
        } catch (\Throwable $e) {
            \Log::error('Failed to send reset password OTP: ' . $e->getMessage());
            return response()->json(['message' => 'Không thể gửi OTP. Vui lòng thử lại sau.'], 500);
        }
    }

    /** Xác thực OTP cho reset password */
    public function verifyOtpForReset(Request $r)
    {
        $data = $r->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'digits:6'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy tài khoản'], 404);
        }

        $key = "reset_otp_{$user->id}";
        $attemptKey = "reset_otp_attempts_{$user->id}";
        $cachedOtp = cache()->get($key);

        if (!$cachedOtp) {
            return response()->json(['message' => 'OTP đã hết hạn. Vui lòng yêu cầu gửi lại.'], 400);
        }

        // Giới hạn số lần nhập sai
        $attempts = (int) cache()->get($attemptKey, 0);
        if ($attempts >= 5) {
            return response()->json(['message' => 'Bạn đã nhập sai quá số lần cho phép. Vui lòng yêu cầu OTP mới.'], 429);
        }

        if ($data['otp'] !== $cachedOtp) {
            cache()->put($attemptKey, $attempts + 1, now()->addMinutes(10));
            return response()->json(['message' => 'OTP không đúng. Vui lòng kiểm tra lại.'], 400);
        }

        // Tạo token để cho phép reset password (valid 10 phút)
        $resetToken = Str::random(60);
        cache()->put("reset_token_{$resetToken}", $user->id, now()->addMinutes(10));

        // Xóa OTP sau khi verify thành công
        cache()->forget($key);
        cache()->forget($attemptKey);

        return response()->json([
            'message' => 'Xác thực OTP thành công',
            'reset_token' => $resetToken,
        ]);
    }

    /** Reset password sau khi verify OTP */
    public function resetPassword(Request $r)
    {
        $data = $r->validate([
            'reset_token' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'string', 'min:6', 'same:new_password'],
        ]);

        // Kiểm tra reset token
        $userId = cache()->get("reset_token_{$data['reset_token']}");
        if (!$userId) {
            return response()->json(['message' => 'Token không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu lại.'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy tài khoản'], 404);
        }

        // Đổi mật khẩu
        $user->password = Hash::make($data['new_password']);
        $user->save();

        // Xóa reset token
        cache()->forget("reset_token_{$data['reset_token']}");

        return response()->json(['message' => 'Đặt lại mật khẩu thành công. Bạn có thể đăng nhập với mật khẩu mới.']);
    }
}
