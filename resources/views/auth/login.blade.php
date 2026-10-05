<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
</head>
<body>
    <main>
        <h1>Login</h1>

        @if ($errors->any())
            <div role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="/login">
            @csrf

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>

            <button type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
