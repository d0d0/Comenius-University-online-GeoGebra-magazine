<!DOCTYPE html>
<html lang="en-US">
    <head>
        <meta charset="utf-8">
    </head>
    <body>
        <h2>Password reset</h2>

        <div>
            <!-- TODO -->
            {{ URL::to('/reset/' . $token) }}.<br/>
        </div>

    </body>
</html>