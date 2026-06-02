<?php
session_start();
include __DIR__.'/init/config.php';

$slug = '';
$invalidAccess = false;

// ১. আপনার অরিজিনাল অভ্যন্তরীণ সেশন টোকেন ও সিকিউরিটি চেক
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = $_POST['stream'] ?? '';
    $token = $_POST['token'] ?? '';
    if (!$slug || !$token || $token !== ($_SESSION['stream_token'] ?? '')) {
        $invalidAccess = true;
    } else {
        unset($_SESSION['stream_token']);
    }
} else {
    $invalidAccess = true;
}

if ($invalidAccess) {
    ?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Error</title>
<style>body{background:#000;color:#f00;font-family:sans-serif;text-align:center;padding-top:50px;}#msg{font-size:24px;}</style>
</head><body>
<div id="msg">System error occurred</div>
<script>
(function(){
    var threshold = 160;
    window.setInterval(function(){
        var width = window.outerWidth - window.innerWidth;
        var height = window.outerHeight - window.innerHeight;
        if(width>threshold || height>threshold) {
            document.body.innerHTML = '<h1>Welcome!</h1><p>Nothing to see here.</p>';
        }
    },500);
})();
</script>
</body></html><?php
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Live Stream Player</title>
    <style>
        html, body {margin:0;padding:0;width:100%;height:100%;background:#000;overflow:hidden;}
        #player-container {width:100%;height:100%;display:flex;justify-content:center;align-items:center;}
        #loading-msg {color: #fff; font-family: sans-serif; font-size: 18px;}
    </style>
    <script src="https://cdn.jwplayer.com/libraries/IDzF9Zmk.js"></script>
</head>
<body>
<div id="player-container">
    <div id="loading-msg">Loading Stream...</div>
    <div id="my-player" style="display:none;"></div>
</div>

<script>
    // পিএইচপি থেকে চ্যানেল স্ল্যাগ জাভাস্ক্রিপ্টে পাস করা হচ্ছে
    var streamSlug = "<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>";

    // ব্রাউজার এন্ড থেকে সরাসরি token.php লোড করার ফাংশন
    async function loadStream() {
        try {
            // বর্তমান হোস্ট ডোমেইন অনুযায়ী token.php এর সঠিক রিলেটিভ পাথ তৈরি
            var tokenApiUrl = window.location.origin + window.location.pathname.replace('home.php', '') + 'auth/token.php?slug=' + encodeURIComponent(streamSlug);
            
            // ব্রাউজারে ব্যাকগ্রাউন্ডে fetch রিকোয়েস্ট পাঠিয়ে জেসন ডাটা নেওয়া
            let response = await fetch(tokenApiUrl);
            let data = await response.json();
            
            if (data.status === 'ok' && data.stream_url) {
                // ২. token.php থেকে যা আসবে, হুবহু সেটাই থাকবে (কোনো পরিবর্তন ছাড়া)
                var finalStreamUrl = data.stream_url; 

                // লোডিং মেসেজ হাইড করে প্লেয়ার কন্টেইনার শো করা
                document.getElementById('loading-msg').style.display = 'none';
                document.getElementById('my-player').style.display = 'block';

                // ৩. JW Player-এ সরাসরি লিঙ্কটি পাঠানো
                initJWPlayer(finalStreamUrl);
            } else {
                document.getElementById('loading-msg').innerText = "Error: Stream URL not found in API response!";
            }
        } catch (error) {
            console.error("Token loading failed:", error);
            document.getElementById('loading-msg').innerText = "Failed to load channel authorization.";
        }
    }

    // JW Player ইনিশিয়ালাইজেশন ফাংশন
    function initJWPlayer(streamUrl) {
        var playerInstance = jwplayer("my-player");
        playerInstance.setup({
            playlist: [{
                sources: [{
                    file: streamUrl,
                    type: "hls" // JW Player কে ফোর্স করা যেন টোকেন ওয়ালা লিঙ্ককে HLS হিসেবেই চালায়
                }]
            }],
            width: "100%",
            height: "100%",
            autostart: true,
            mute: false,
            controls: true,
            stretching: "uniform",
            androidhls: true, 
            primary: "html5", 
            repeat: false
        });

        // অটো-প্লে পলিসি ফেইল হ্যান্ডলার
        playerInstance.on('autoplayFailed', function() {
            playerInstance.setMute(true);
            playerInstance.play();
        });
    }

    // পেজ লোড সম্পূর্ণ হলে রান হবে
    window.onload = loadStream;
</script>
</body>
</html>