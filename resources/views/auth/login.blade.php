<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login &bull; Guardian Orange Cat Payroll</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Smooth Rive-Style Spring Transitions */
        .cat-spring {
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .paw-spring {
            transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .face-element {
            transition: all 0.3s ease-out;
        }

        /* Subtle idle breathing & ear twitch */
        @keyframes idleBreathe {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }
        .cat-idle-body {
            animation: idleBreathe 3.5s ease-in-out infinite;
        }

        @keyframes twitchEar {
            0%, 90%, 100% { transform: rotate(0deg); }
            93% { transform: rotate(-5deg); }
            96% { transform: rotate(0deg); }
        }
        .cat-twitch-left {
            transform-origin: 40px 30px;
            animation: twitchEar 5s ease-in-out infinite;
        }

        /* Head shake on fail */
        @keyframes failShake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-8px) rotate(-2deg); }
            40%, 80% { transform: translateX(8px) rotate(2deg); }
        }
        .shake-animation {
            animation: failShake 0.5s ease-in-out;
        }

        /* Happy bounce on success */
        @keyframes happyBounce {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-12px) scale(1.05); }
        }
        .happy-animation {
            animation: happyBounce 0.6s ease-in-out infinite;
        }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased font-sans select-none overflow-x-hidden"
      style="background: radial-gradient(circle at 50% 30%, #1e293b 0%, #0f1b2d 60%, #080f1a 100%);">

    <!-- Glowing ambient background lights -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-orange-500/10 rounded-full blur-[140px] pointer-events-none"></div>
    <div class="absolute bottom-10 right-10 w-96 h-96 bg-blue-500/5 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="w-full max-w-[420px] relative z-10 flex flex-col items-center"
         x-data="guardianCatLogin()">

        <!-- ============================================================== -->
        <!-- GUARDIAN ORANGE TABBY CAT CHARACTER (RIVE / YETI POLAR BEAR STYLE) -->
        <!-- ============================================================== -->
        <div class="relative w-[270px] h-[220px] -mb-9 z-20 pointer-events-none"
             :class="{ 'shake-animation': animState === 'fail', 'happy-animation': animState === 'success' }">

            <svg viewBox="0 0 260 220" class="w-full h-full overflow-visible drop-shadow-2xl" fill="none" xmlns="http://www.w3.org/2000/svg">
                
                <!-- 1. BASE BODY & COZY RED WINTER SCARF BACK -->
                <g class="cat-idle-body">
                    <!-- Chubby Cat Body -->
                    <ellipse cx="130" cy="180" rx="68" ry="46" fill="#F97316"/>
                    <ellipse cx="130" cy="188" rx="46" ry="32" fill="#FFF7ED"/>

                    <!-- Body Stripes -->
                    <path d="M96 160 Q104 172 96 186" stroke="#C2410C" stroke-width="3.5" stroke-linecap="round"/>
                    <path d="M164 160 Q156 172 164 186" stroke="#C2410C" stroke-width="3.5" stroke-linecap="round"/>
                </g>

                <!-- 2. CAT HEAD GROUP (Pans & Tilts with Caret) -->
                <g id="cat-head" 
                   class="cat-spring"
                   :style="getHeadTransform()">

                    <!-- Left Ear -->
                    <g class="cat-twitch-left">
                        <polygon points="58,95 72,28 112,68" fill="#F97316" stroke="#C2410C" stroke-width="2"/>
                        <polygon points="66,88 78,40 104,68" fill="#FDA4AF"/>
                    </g>

                    <!-- Right Ear -->
                    <g>
                        <polygon points="202,95 188,28 148,68" fill="#F97316" stroke="#C2410C" stroke-width="2"/>
                        <polygon points="194,88 182,40 156,68" fill="#FDA4AF"/>
                    </g>

                    <!-- Main Chubby Head -->
                    <ellipse cx="130" cy="98" rx="62" ry="52" fill="#FB923C"/>
                    
                    <!-- Cream Muzzle Area -->
                    <ellipse cx="130" cy="114" rx="42" ry="32" fill="#FFF7ED"/>

                    <!-- Tabby Forehead Stripes (Classic M) -->
                    <path d="M130 52 L130 72" stroke="#C2410C" stroke-width="4" stroke-linecap="round"/>
                    <path d="M116 56 L122 74" stroke="#C2410C" stroke-width="3.5" stroke-linecap="round"/>
                    <path d="M144 56 L138 74" stroke="#C2410C" stroke-width="3.5" stroke-linecap="round"/>

                    <!-- Cheek Stripes -->
                    <path d="M76 98 L92 100" stroke="#EA580C" stroke-width="3" stroke-linecap="round"/>
                    <path d="M78 108 L94 108" stroke="#EA580C" stroke-width="3" stroke-linecap="round"/>
                    <path d="M184 98 L168 100" stroke="#EA580C" stroke-width="3" stroke-linecap="round"/>
                    <path d="M182 108 L166 108" stroke="#EA580C" stroke-width="3" stroke-linecap="round"/>

                    <!-- Cozy Red Winter Scarf (Front Loop & Knot) -->
                    <path d="M72 136 C72 136, 100 154, 130 154 C160 154, 188 136, 188 136 C194 146, 175 162, 130 162 C85 162, 66 146, 72 136 Z" fill="#DC2626" stroke="#991B1B" stroke-width="2"/>
                    <!-- Scarf Tails -->
                    <path d="M142 152 L154 186 L174 184 L160 150 Z" fill="#B91C1C"/>
                    <!-- Scarf Stripe Texture -->
                    <line x1="148" y1="172" x2="166" y2="170" stroke="#FEF08A" stroke-width="3"/>
                    <line x1="151" y1="180" x2="170" y2="178" stroke="#FEF08A" stroke-width="3"/>

                    <!-- Whiskers -->
                    <line x1="56" y1="116" x2="16" y2="112" stroke="#78350F" stroke-width="1.8" stroke-linecap="round"/>
                    <line x1="54" y1="124" x2="14" y2="128" stroke="#78350F" stroke-width="1.8" stroke-linecap="round"/>
                    <line x1="204" y1="116" x2="244" y2="112" stroke="#78350F" stroke-width="1.8" stroke-linecap="round"/>
                    <line x1="206" y1="124" x2="246" y2="128" stroke="#78350F" stroke-width="1.8" stroke-linecap="round"/>

                    <!-- Cute Eyebrows -->
                    <g id="cat-eyebrows" class="face-element">
                        <!-- Idle / Happy / Tracking Eyebrows -->
                        <path x-show="animState !== 'fail'" d="M96 78 Q108 74 116 78" stroke="#9A3412" stroke-width="2.5" stroke-linecap="round"/>
                        <path x-show="animState !== 'fail'" d="M144 78 Q152 74 164 78" stroke="#9A3412" stroke-width="2.5" stroke-linecap="round"/>
                        <!-- Sad / Disappointed Worried Eyebrows -->
                        <path x-show="animState === 'fail'" x-cloak d="M98 74 Q108 80 116 78" stroke="#9A3412" stroke-width="3" stroke-linecap="round"/>
                        <path x-show="animState === 'fail'" x-cloak d="M144 78 Q152 80 162 74" stroke="#9A3412" stroke-width="3" stroke-linecap="round"/>
                    </g>

                    <!-- Cute Blushing Cheeks -->
                    <ellipse cx="90" cy="116" rx="8" ry="4.5" fill="#FDA4AF" :opacity="animState === 'success' ? '0.9' : '0.45'"/>
                    <ellipse cx="170" cy="116" rx="8" ry="4.5" fill="#FDA4AF" :opacity="animState === 'success' ? '0.9' : '0.45'"/>

                    <!-- 3. EYES & PUPILS GROUP -->
                    <g id="cat-eyes">
                        <!-- Left Eye Socket -->
                        <ellipse cx="106" cy="95" rx="14" ry="16" fill="#FFFFFF" stroke="#451A03" stroke-width="2"/>
                        <!-- Right Eye Socket -->
                        <ellipse cx="154" cy="95" rx="14" ry="16" fill="#FFFFFF" stroke="#451A03" stroke-width="2"/>

                        <!-- Pupils (Tracks caret position dynamically) -->
                        <!-- When Sad/Fail, look downward -->
                        <!-- When Happy/Success, big sparkles -->
                        <g id="cat-pupils" class="face-element">
                            <!-- Left Pupil -->
                            <ellipse :cx="106 + pupilX" :cy="95 + pupilY" rx="8" ry="10" fill="#0F172A"/>
                            <circle :cx="103 + pupilX" :cy="91 + pupilY" r="3.2" fill="#FFFFFF"/>
                            <circle :cx="109 + pupilX" :cy="98 + pupilY" r="1.6" fill="#FFFFFF"/>

                            <!-- Right Pupil -->
                            <ellipse :cx="154 + pupilX" :cy="95 + pupilY" rx="8" ry="10" fill="#0F172A"/>
                            <circle :cx="151 + pupilX" :cy="91 + pupilY" r="3.2" fill="#FFFFFF"/>
                            <circle :cx="157 + pupilX" :cy="98 + pupilY" r="1.6" fill="#FFFFFF"/>
                        </g>

                        <!-- Happy Curved Eyes (Success overlay) -->
                        <g x-show="animState === 'success'" x-cloak>
                            <path d="M94 95 Q106 82 118 95" stroke="#451A03" stroke-width="4.5" stroke-linecap="round" fill="none"/>
                            <path d="M142 95 Q154 82 166 95" stroke="#451A03" stroke-width="4.5" stroke-linecap="round" fill="none"/>
                        </g>
                    </g>

                    <!-- 4. NOSE & DYNAMIC MOUTH GROUP -->
                    <!-- Pink Nose -->
                    <polygon points="124,111 136,111 130,117" fill="#F43F5E"/>
                    <path d="M130 117 L130 121" stroke="#451A03" stroke-width="2" stroke-linecap="round"/>

                    <!-- Dynamic Mouth Shapes -->
                    <g id="cat-mouth">
                        <!-- State A: Idle / Typing Cute 'w' Mouth -->
                        <path x-show="animState !== 'fail' && animState !== 'success'" 
                              d="M121 121 Q130 126 130 121 Q130 126 139 121" 
                              stroke="#451A03" stroke-width="2.5" stroke-linecap="round" fill="none"/>

                        <!-- State B: Success Joyful Open Grin :D -->
                        <g x-show="animState === 'success'" x-cloak>
                            <path d="M118 121 Q130 138 142 121 Z" fill="#BE123C" stroke="#451A03" stroke-width="2"/>
                            <!-- Cute Tongue -->
                            <path d="M124 128 Q130 137 136 128 Z" fill="#FDA4AF"/>
                        </g>

                        <!-- State C: Sad / Disappointed Downturned Frown ︵ -->
                        <path x-show="animState === 'fail'" x-cloak 
                              d="M120 128 Q130 120 140 128" 
                              stroke="#451A03" stroke-width="3" stroke-linecap="round" fill="none"/>
                    </g>

                </g> <!-- End cat-head -->

                <!-- 5. RIGGED PAWS (Spring Upward to Cover Eyes & Cheeky Peek) -->
                <!-- Left Paw -->
                <g id="cat-paw-left" 
                   class="paw-spring"
                   :style="getLeftPawTransform()">
                    <!-- Paw Base -->
                    <ellipse cx="68" cy="188" rx="20" ry="16" fill="#FB923C" stroke="#EA580C" stroke-width="2"/>
                    <!-- Pink Toe-Beans -->
                    <ellipse cx="68" cy="190" rx="9" ry="7" fill="#FDA4AF"/>
                    <circle cx="58" cy="180" r="3.2" fill="#FDA4AF"/>
                    <circle cx="68" cy="177" r="3.5" fill="#FDA4AF"/>
                    <circle cx="78" cy="180" r="3.2" fill="#FDA4AF"/>
                </g>

                <!-- Right Paw -->
                <g id="cat-paw-right" 
                   class="paw-spring"
                   :style="getRightPawTransform()">
                    <!-- Paw Base -->
                    <ellipse cx="192" cy="188" rx="20" ry="16" fill="#FB923C" stroke="#EA580C" stroke-width="2"/>
                    <!-- Pink Toe-Beans -->
                    <ellipse cx="192" cy="190" rx="9" ry="7" fill="#FDA4AF"/>
                    <circle cx="182" cy="180" r="3.2" fill="#FDA4AF"/>
                    <circle cx="192" cy="177" r="3.5" fill="#FDA4AF"/>
                    <circle cx="202" cy="180" r="3.2" fill="#FDA4AF"/>
                </g>

            </svg>
        </div>

        <!-- ============================================================== -->
        <!-- CLEAN WHITE ROUNDED LOGIN CARD (Overlapped naturally by Cat Paws) -->
        <!-- ============================================================== -->
        <div class="w-full bg-white rounded-3xl p-8 shadow-2xl shadow-slate-950/80 border border-slate-100 relative z-10">

            <!-- Card Header -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-black tracking-tight text-slate-900">
                    Kucing Oren <span class="text-orange-600">Payroll</span>
                </h1>
                <p class="text-xs text-slate-400 mt-1 font-medium">Sistem Penggajian & Insentif SPG (Dynamic Daily Outlets)</p>
            </div>

            <!-- Error Banner -->
            <div x-show="errorMessage" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 class="mb-4 p-3 bg-red-50 border border-red-200 rounded-2xl flex items-center gap-2.5 text-red-700 text-xs shadow-xs">
                <span class="text-base">😿</span>
                <span class="font-medium" x-text="errorMessage"></span>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submitLogin($event)" class="space-y-4">
                
                <!-- Email / Username Field -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                        <span>Email atau Username</span>
                        <span class="text-[10px] text-orange-500 font-semibold" x-show="animState === 'typing_email'">👀 Cat membaca teks...</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input type="text" 
                               name="login" 
                               x-model="email"
                               @focus="handleEmailFocus()"
                               @blur="handleBlur()"
                               @input="handleEmailInput($event)"
                               placeholder="admin@payroll.local atau admin" 
                               required 
                               autocomplete="username"
                               autofocus
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center justify-between">
                        <span>Password</span>
                        <span class="text-[10px] text-orange-600 font-semibold" x-show="animState === 'password' && !showPassword">🙈 Cakar menutup mata!</span>
                        <span class="text-[10px] text-orange-600 font-semibold" x-show="animState === 'password' && showPassword">🫣 Mengintip 1 mata!</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input :type="showPassword ? 'text' : 'password'" 
                               name="password" 
                               x-model="password"
                               @focus="handlePasswordFocus()"
                               @blur="handleBlur()"
                               placeholder="••••••••••••" 
                               required 
                               autocomplete="current-password"
                               class="w-full pl-10 pr-11 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all">
                        
                        <!-- Eye / Eye-Slash Toggle Button (Cheeky One-Eye Peek Trigger) -->
                        <button type="button" 
                                @click="togglePasswordVisibility()" 
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-orange-600 transition cursor-pointer"
                                title="Lihat Password">
                            <!-- Hidden Eye Icon -->
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <!-- Revealed Eye-Slash Icon -->
                            <svg x-show="showPassword" x-cloak class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Quick Admin Autofill -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center space-x-2 text-xs text-slate-500 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded text-orange-600 focus:ring-orange-500 border-slate-300">
                        <span>Ingat Perangkat</span>
                    </label>

                    <button type="button" 
                            @click="fillAdmin()" 
                            class="text-xs text-orange-600 hover:text-orange-700 font-bold underline underline-offset-2">
                        Isi Akun Admin
                    </button>
                </div>

                <!-- Submit Button (#1e3a5f Dark Navy Rounded Button per Spec) -->
                <div class="pt-2">
                    <button type="submit" 
                            style="background-color: #1e3a5f;"
                            class="w-full py-3 px-4 hover:bg-[#162b47] text-white font-bold rounded-2xl text-sm shadow-xl shadow-slate-900/20 active:scale-[0.98] transition-all flex items-center justify-center gap-2 cursor-pointer">
                        <span>Masuk ke Dashboard</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Card Bottom Hint -->
            <div class="mt-6 pt-4 border-t border-slate-100 text-center">
                <p class="text-[11px] text-slate-400">
                    Default: <code class="font-mono text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">admin@payroll.local</code> / <code class="font-mono text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">password123</code>
                </p>
            </div>

            <!-- ============================================================== -->
            <!-- STATE 5: LOADING SPINNER OVERLAY (~600ms matching reference video) -->
            <!-- ============================================================== -->
            <div x-show="isLoading" 
                 x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="absolute inset-0 bg-white/80 backdrop-blur-xs rounded-3xl flex flex-col items-center justify-center z-30">
                <div class="w-12 h-12 rounded-full border-4 border-slate-200 border-t-orange-600 animate-spin mb-3"></div>
                <p class="text-xs font-bold text-slate-800">Memverifikasi Akun...</p>
                <p class="text-[10px] text-slate-400 mt-0.5">Kucing oren sedang memeriksa data</p>
            </div>

        </div>

    </div>

    <!-- Alpine.js Character Rig Controller -->
    <script>
    function guardianCatLogin() {
        return {
            email: '',
            password: '',
            showPassword: false,
            animState: 'idle', // 'idle', 'typing_email', 'password', 'fail', 'success'
            isLoading: false,
            errorMessage: '{{ $errors->first('login') }}',
            pupilX: 0,
            pupilY: 0,
            headTilt: 0,
            headPan: 0,

            init() {
                if (this.errorMessage) {
                    this.animState = 'fail';
                    this.pupilY = 6; // eyes look down somberly
                }
            },

            // State 2: Focus & Input on Email Field
            handleEmailFocus() {
                if (this.animState === 'fail') this.errorMessage = '';
                this.animState = 'typing_email';
                this.updateCaretTracking();
            },

            handleEmailInput(e) {
                this.updateCaretTracking();
            },

            // Dynamic Caret Tracking Left-to-Right
            updateCaretTracking() {
                const len = Math.min(this.email.length, 30);
                // Map length (0 to 30) from left (-14px) to right (+14px)
                const ratio = (len / 30) * 2 - 1; // -1 to +1
                this.headPan = ratio * 14;
                this.headTilt = ratio * 4.5;
                // Pupils track even further
                this.pupilX = ratio * 8.5;
                this.pupilY = 4; // looks slightly downward toward input box
            },

            // State 3 & 4: Focus on Password Field
            handlePasswordFocus() {
                if (this.animState === 'fail') this.errorMessage = '';
                this.animState = 'password';
                this.headPan = 0;
                this.headTilt = 0;
                this.pupilX = this.showPassword ? 5 : 0;
                this.pupilY = 0;
            },

            // State 4: Toggle Show Password (Cheeky One-Eye Peek)
            togglePasswordVisibility() {
                this.showPassword = !this.showPassword;
                if (this.showPassword) {
                    // Right eye peeks at the password
                    this.pupilX = 5;
                    this.pupilY = 2;
                } else {
                    this.pupilX = 0;
                    this.pupilY = 0;
                }
            },

            handleBlur() {
                // If not submitting or failed, return to idle
                if (this.animState !== 'fail' && this.animState !== 'success' && !this.isLoading) {
                    this.animState = 'idle';
                    this.headPan = 0;
                    this.headTilt = 0;
                    this.pupilX = 0;
                    this.pupilY = 0;
                }
            },

            fillAdmin() {
                this.email = 'admin@payroll.local';
                this.password = 'password123';
                this.handleEmailFocus();
            },

            // Head Transform calculation
            getHeadTransform() {
                if (this.animState === 'typing_email') {
                    return `transform: translate(${this.headPan}px, 6px) rotate(${this.headTilt}deg); transform-origin: 130px 100px;`;
                }
                if (this.animState === 'fail') {
                    return 'transform: translateY(8px);';
                }
                if (this.animState === 'success') {
                    return 'transform: translateY(-4px);';
                }
                return 'transform: translate(0, 0);';
            },

            // Rigged Paw Transform calculations
            getLeftPawTransform() {
                // When in password field, left paw ALWAYS covers left eye completely
                if (this.animState === 'password') {
                    return 'transform: translate(38px, -92px) rotate(16deg);';
                }
                // When success, paws raise in celebration
                if (this.animState === 'success') {
                    return 'transform: translate(-10px, -24px) rotate(-15deg);';
                }
                // Default rest position
                return 'transform: translate(0, 0);';
            },

            getRightPawTransform() {
                // When in password field:
                if (this.animState === 'password') {
                    if (this.showPassword) {
                        // Cheeky one-eye peek: lower right paw halfway down!
                        return 'transform: translate(-20px, -45px) rotate(-18deg);';
                    } else {
                        // Completely cover right eye!
                        return 'transform: translate(-38px, -92px) rotate(-16deg);';
                    }
                }
                if (this.animState === 'success') {
                    return 'transform: translate(10px, -24px) rotate(15deg);';
                }
                // Default rest position
                return 'transform: translate(0, 0);';
            },

            // State 5 & 6: Submit via AJAX to demonstrate smooth loading & reactions
            async submitLogin(e) {
                this.animState = 'idle'; // paws drop back down
                this.isLoading = true;
                this.errorMessage = '';

                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const startTime = Date.now();

                try {
                    const response = await fetch('/login', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            login: this.email,
                            password: this.password
                        })
                    });

                    // Ensure loading overlay shows for ~600ms matching video pacing
                    const elapsed = Date.now() - startTime;
                    if (elapsed < 600) {
                        await new Promise(r => setTimeout(r, 600 - elapsed));
                    }

                    this.isLoading = false;

                    if (response.ok) {
                        const data = await response.json();
                        // State 6: Happy / Excited State!
                        this.animState = 'success';
                        setTimeout(() => {
                            window.location.href = data.redirect || '/tasks';
                        }, 500);
                    } else {
                        const err = await response.json();
                        // State 6: Sad / Disappointed State!
                        this.animState = 'fail';
                        this.pupilY = 6;
                        this.errorMessage = err.message || 'Username/Password salah!';
                    }
                } catch (error) {
                    this.isLoading = false;
                    this.animState = 'fail';
                    this.errorMessage = 'Terjadi kesalahan jaringan.';
                }
            }
        };
    }
    </script>
</body>
</html>
