<x-guest-layout>
    @php
        // 1. Locate the JSON file
        $jsonPath = storage_path('app/quotes.json');
        
        // 2. Default fallback values
        $quoteText = 'Stay curious.';
        $quoteAuthor = 'Unknown';

        // 3. Read and parse the JSON
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            $quotesData = json_decode($jsonContent, true);
            $selectedQuote = null;
            
            // Handle if JSON starts with {"quotes": [...]}
            if (isset($quotesData['quotes']) && is_array($quotesData['quotes']) && count($quotesData['quotes']) > 0) {
                $selectedQuote = $quotesData['quotes'][array_rand($quotesData['quotes'])];
            } 
            // Handle if JSON starts directly with [...]
            elseif (is_array($quotesData) && count($quotesData) > 0) {
                $selectedQuote = $quotesData[array_rand($quotesData)];
            }

            // 4. Map the keys safely (some JSON files use 'text' instead of 'quote')
            if ($selectedQuote) {
                $quoteText = $selectedQuote['quote'] ?? $selectedQuote['text'] ?? $quoteText;
                $quoteAuthor = $selectedQuote['author'] ?? $quoteAuthor;
            }
        }
    @endphp

    <div class="login-card">
        
        <!-- Minimalist Brand Header -->
        <div class="brand-logo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M16 21V5C16 4.46957 15.7893 3.96086 15.4142 3.58579C15.0391 3.21071 14.5304 3 14 3H10C9.46957 3 8.96086 3.21071 8.58579 3.58579C8.21071 3.96086 8 4.46957 8 5V21" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Art Caravan PH Inventory</span>
        </div>

        <h5 class="text-center mb-1 fw-bold" style="color: #1e293b;">Welcome Back</h5>
        <p class="text-center text-muted small mb-4">Please sign-in to access the dashboard</p>

        <!-- Session Status -->
        

        <!-- Validation Errors -->
        

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Username Field -->
            <div class="mb-3">
                <label for="username" class="form-label small fw-semibold text-secondary">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" value="{{ old('username') }}" required autofocus>
            </div>

            <!-- Password Field -->
            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                    <span class="password-toggle" onclick="togglePassword()">
                    </span>
                </div>
            </div>

            <!-- Submit Login Action -->
            <button type="submit" class="btn btn-blue w-100 text-uppercase tracking-wide small mb-3">
                Sign In
            </button>
        </form>

        <!-- Daily Wisdom Quote -->
        <div class="mt-3 pt-3 border-top text-center">
            <p class="fst-italic text-muted small mb-1">"{{ $quoteText }}"</p>
            <small class="fw-bold text-secondary" style="font-size: 0.75rem;">— {{ $quoteAuthor }}</small>
        </div>

    </div>
</x-guest-layout>
