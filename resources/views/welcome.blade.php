<!DOCTYPE html>
<html>
    <title>Laravel</title>

    <body>
        <h1>
            Hello {{ $user['given_name'] }}, you're logged in using {{ $user['email'] }}.
        </h1>
    <a href="{{ $logoutUrl }}">Logout</a>
    </body>
</html>
