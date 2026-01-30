<div class="sb-login-page">
  <div class="sb-login-card">
    <h1 class="sb-login-title">Welcome back</h1>
    <p class="sb-login-subtitle">Sign in to manage your classroom updates.</p>
    <form method="post" action="/login" class="sb-login-stack">
      @csrf
      <label class="sb-login-field">
        Phone
        <input type="text" name="phone" class="sb-login-input" placeholder="0500000000" autocomplete="tel">
      </label>
      <label class="sb-login-field">
        Password
        <input type="password" name="password" class="sb-login-input" placeholder="••••••••" autocomplete="current-password">
      </label>
      <button type="submit" class="sb-login-button">Sign in</button>
    </form>
    <div class="sb-login-links">
      <a href="/auth/code">Use one-time code</a>
      <a href="/">Back to site</a>
    </div>
  </div>
</div>
