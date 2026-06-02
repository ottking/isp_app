<?php
session_start();
include __DIR__.'/init/config.php';

// ১. টোকেন জেনারেশন হ্যান্ডলার
if (isset($_GET['token'])) {
    $new = bin2hex(random_bytes(16));
    $_SESSION['stream_token'] = $new;
    header('Content-Type: application/json');
    echo json_encode(['token' => $new]);
    exit;
}

$page_token = bin2hex(random_bytes(16));
$_SESSION['stream_token'] = $page_token;

// ২. ডাটাবেস থেকে ক্যাটাগরি আনা (অর্ডারিং অনুযায়ী)
$cat_query = $db->query("SELECT * FROM categories ORDER BY ordering ASC");

// ৩. চ্যানেলগুলোকে ক্যাটাগরি অর্ডারিং অনুযায়ী সাজানো
$channel_sql = "SELECT channels.*, categories.ordering 
                FROM channels 
                LEFT JOIN categories ON channels.category_id = categories.cat_id 
                WHERE channels.status = 'active' 
                ORDER BY categories.ordering ASC, channels.id DESC";

$channel_query = $db->query($channel_sql);

// ৪. নোটিফিকেশন লজিক
$current_time = date('Y-m-d H:i:s');
$notify_res = $db->query("SELECT * FROM notifications WHERE status = 'active' AND end_time > '$current_time' ORDER BY id DESC");
$all_notifications = [];
while($row = $notify_res->fetchArray(SQLITE3_ASSOC)) {
    $all_notifications[] = $row;
}

// ৫. অটো-লোডের জন্য প্রথম স্লাগ
$first_channel_res = $db->query($channel_sql . " LIMIT 1");
$first_ch = $first_channel_res->fetchArray(SQLITE3_ASSOC);
$first_slug = ($first_ch) ? $first_ch['channel_slug'] : '';

// ৬. সেটিংস
$settings_res = $db->query("SELECT * FROM settings WHERE key IN ('sys_logo', 'app_mobile', 'app_tv')");
$sys_settings = [];
while($row = $settings_res->fetchArray(SQLITE3_ASSOC)) {
    $sys_settings[$row['key']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTT - KING | Premium IPTV</title>

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="style.css">

    <script src="assets/js/vendor/jquery-1.12.0.min.js"></script>
    <script>var pageToken = '<?= $page_token ?>';</script>
    
    <style>
        .ch-img { position: relative; }
        .fav-icon {
            position: absolute; top: 10px; right: 10px; z-index: 100; cursor: pointer;
            background: rgba(0,0,0,0.5); width: 30px; height: 30px; line-height: 32px;
            text-align: center; border-radius: 50%; transition: 0.3s;
        }
        .fav-icon i { color: #fff; font-size: 14px; }
        .channel.is-fav .fav-icon i { color: #ff2e2e; }
        .channel.is-fav { border: 2px solid #ff2e2e; }
        .channel { position: relative; }
        .channel.playing::before {
            content: "PLAYING"; position: absolute; top: 0; left: 0; width: 100%;
            text-align: center; background: rgba(0,0,0,0.6); color: #fff;
            font-size: 12px; padding: 2px 0; z-index: 10;
        }
        .main-footer {
            background: #111; color: #aaa; padding: 40px 0; border-top: 1px solid #222;
            margin-top: 50px; text-align: center;
        }
        .footer-info p { margin-bottom: 5px; font-size: 14px; }
        .footer-info a { color: #ff2e2e !important; text-decoration: none; font-weight: 600; }
        .footer-info .contact-icons { margin-top: 10px; font-weight: 600; color: #eee; }

        .ott-notice-bar {
            background: #111; border-top: 2px solid #ff2e2e; padding: 10px 0;
            margin-top: -10px; margin-bottom: 20px; position: relative;
        }
        .notice-container { display: flex; align-items: center; }
        .notice-head {
            color: #ff2e2e; font-weight: 600; padding-right: 15px;
            border-right: 1px solid #333; margin-right: 15px; font-size: 14px;
            text-transform: uppercase;
        }
        .notice-body { flex: 1; overflow: hidden; white-space: nowrap; }
        .notice-msg { color: #ccc; font-size: 14px; margin-right: 80px; display: inline-block; }

        .player-box.mini {
            position: fixed; bottom: 10px; right: 10px; width: 300px; height: 175px;
            padding-bottom: 0; max-width: none; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            z-index: 10000; transition: all 0.3s ease-in-out;
        }
        .player-box.mini .player { width: 300px; height: 175px; }

        .search-container { margin-bottom: 15px; width: 100%; max-width: 350px; }
        .search-inner { position: relative; }
        .search-inner input {
            width: 100%; background: #161616; border: 1px solid #2e2e2e; color: #fff;
            padding: 10px 15px 10px 40px; border-radius: 5px; outline: none;
        }
        .search-inner i { position: absolute; left: 15px; top: 13px; color: #ff2e2e; }
    </style>
</head>

<body class="iptv-body">
<header class="header">
    <div class="container-fluid">
        <div class="header-wrapper">
            <div class="logo">
                <?php if (!empty($sys_settings['sys_logo'])): ?>
                    <img src="<?= $sys_settings['sys_logo'] ?>" alt="ottking" style="max-height: 40px;">
                <?php else: ?>
                    <i class="fa fa-play-circle"></i> <span>OTT - KING</span>
                <?php endif; ?>
            </div>
            <div class="app-downloads">
                <?php if(!empty($sys_settings['app_mobile'])): ?><a href="<?= $sys_settings['app_mobile'] ?>" class="app-btn mobile-app animate-pulse"><i class="fa fa-mobile"></i> Mobile</a><?php endif; ?>
                <?php if(!empty($sys_settings['app_tv'])): ?><a href="<?= $sys_settings['app_tv'] ?>" class="app-btn tv-app animate-pulse"><i class="fa fa-television"></i> TV App</a><?php endif; ?>
            </div>
        </div>
    </div>
</header>

<section class="player-section">
    <div class="container-fluid">
        <div class="player-box">
            <iframe name="tv" class="player" scrolling="no" src="about:blank" allow="autoplay; encrypted-media" allowfullscreen></iframe>
        </div>
    </div>
</section>

<form id="streamForm" method="POST" action="home.php" target="tv" style="display:none;">
    <input type="hidden" name="stream" id="streamInput" value="" />
    <input type="hidden" name="token" id="tokenInput" value="" />
</form>

<?php if (count($all_notifications) > 0): ?>
<div class="ott-notice-bar">
    <div class="container-fluid">
        <div class="notice-container">
            <div class="notice-head"><i class="fa fa-bullhorn"></i> Update</div>
            <div class="notice-body">
                <marquee behavior="scroll" direction="left" onmouseover="this.stop();" onmouseout="this.start();">
                    <?php foreach($all_notifications as $note): ?>
                        <span class="notice-msg"><b><?= htmlspecialchars($note['title']) ?>:</b> <?= htmlspecialchars($note['msg']) ?></span>
                    <?php endforeach; ?>
                </marquee>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="content-section">
    <div class="container-fluid">
        <div class="section-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <h2 class="section-title">Live Channels</h2>
                
                <div class="search-container">
                    <div class="search-inner">
                        <i class="fa fa-search"></i>
                        <input type="text" id="channelSearch" placeholder="Search Channels...">
                    </div>
                </div>
            </div>

            <div class="filters">
                <button class="filter-btn active" data-filter="*">All</button>
                <button class="filter-btn" data-filter=".is-fav"><i class="fa fa-heart"></i> FAVORITE</button>
                <?php 
                $cat_query->reset(); 
                while($cat = $cat_query->fetchArray(SQLITE3_ASSOC)): 
                ?>
                    <button class="filter-btn" data-filter=".cat-<?= $cat['cat_id'] ?>"><?= $cat['cat_name'] ?></button>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="channels-grid">
            <?php while($ch = $channel_query->fetchArray(SQLITE3_ASSOC)): ?>
            <div class="channel item cat-<?= $ch['category_id'] ?>" data-slug="<?= $ch['channel_slug'] ?>" data-name="<?= strtolower($ch['channel_name']) ?>">
                <div class="ch-img">
                    <div class="fav-icon" onclick="toggleFav('<?= $ch['channel_slug'] ?>')"><i class="fa fa-heart"></i></div>
                    <img src="<?= !empty($ch['logo']) ? $ch['logo'] : 'assets/images/default.jpg' ?>" alt="<?= $ch['channel_name'] ?>">
                    <a href="#" target="tv"></a>
                </div>
                <div class="ch-info"><h3><?= $ch['channel_name'] ?></h3></div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<footer class="main-footer">
    <div class="container">
        <div class="footer-info">
            <p>&copy; 2026 <strong>OTT - KING</strong> - Premium IPTV Streaming</p>
            <p>Website: <a href="https://ottking.top" target="_blank">ottking.top</a></p>
            <div class="contact-icons">
                <span>📞 Phone: 01315124700</span> | 
                <span>📱 Telegram: <a href="https://t.me/im_rasel" target="_blank">@im_rasel</a></span>
            </div>
        </div>
    </div>
</footer>

<script src="assets/js/main.js"></script>
<script>
    let favorites = JSON.parse(localStorage.getItem('ott_favs')) || [];
    function updateFavUI() {
        $('.channel').each(function() {
            if (favorites.includes($(this).data('slug'))) $(this).addClass('is-fav');
            else $(this).removeClass('is-fav');
        });
    }
    function toggleFav(slug) {
        if (favorites.includes(slug)) favorites = favorites.filter(s => s !== slug);
        else favorites.push(slug);
        localStorage.setItem('ott_favs', JSON.stringify(favorites));
        updateFavUI();
    }

    $(document).ready(function() {
        updateFavUI();

        $('.filter-btn').on('click', function() {
            $('#channelSearch').val('');
            $('.filter-btn').removeClass('active'); $(this).addClass('active');
            var val = $(this).attr('data-filter');
            val === '*' ? $('.channel').fadeIn(400) : ($('.channel').hide(), $(val).fadeIn(400));
        });

        $('#channelSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('.filter-btn').removeClass('active');
            $('.filter-btn[data-filter="*"]').addClass('active');
            $(".channel").each(function() {
                $(this).toggle($(this).data('name').includes(value));
            });
        });

        // প্লে লজিক
        $('.channels-grid').on('click', '.channel a', function(e) {
            e.preventDefault();
            $('.channel.playing').removeClass('playing');
            $(this).closest('.channel').addClass('playing');
            var slug = $(this).closest('.channel').data('slug');
            fetch('index.php?token=1')
                .then(r => r.json())
                .then(data => {
                    $('#streamInput').val(slug);
                    $('#tokenInput').val(data.token);
                    $('#streamForm').submit();
                });
        });

        // অটো-লোড প্রথম চ্যানেল
        var firstSlug = '<?= $first_slug ?>';
        if (firstSlug) {
            $('#streamInput').val(firstSlug);
            $('#tokenInput').val(pageToken);
            $('#streamForm').submit();
            $('.channel[data-slug="'+firstSlug+'"]').addClass('playing');
        }

        let isMini = false;
        const playerSection = $('.player-section');
        const playerBox = $('.player-box');
        $(window).on('scroll', function() {
            if ($(window).scrollTop() > playerSection.height() && !isMini) {
                playerBox.addClass('mini'); isMini = true;
            } else if ($(window).scrollTop() <= playerSection.height() && isMini) {
                playerBox.removeClass('mini'); isMini = false;
            }
        });
    });

    // সিকিউরিটি স্ক্রিপ্ট
    (function(){
        var threshold = 160;
        function isMobile() { return /iPhone|iPad|iPod|Android/i.test(navigator.userAgent); }
        function resizeCheck() { return (window.outerWidth - window.innerWidth) > threshold || (window.outerHeight - window.innerHeight) > threshold; }
        function consoleCheck() {
            var dev = false;
            try {
                var element = new Image();
                Object.defineProperty(element, 'id', { get: function() { dev = true; } });
                console.log(element);
            } catch (e) {}
            return dev;
        }
        function devtoolsOpen() { return isMobile() ? consoleCheck() : (resizeCheck() || consoleCheck()); }
        window.setInterval(function(){
            if (devtoolsOpen()) {
                document.body.innerHTML = '<h1>Welcome!</h1><p>Nothing to see here.</p>';
            }
        }, 500);
    })();
</script>
</body>
</html>