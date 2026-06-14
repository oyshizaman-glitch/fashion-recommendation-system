<?php
include_once 'auth.php';
require_login();
include_once 'db.php';
include_once 'csrf.php';
// load logged in user (may contain only minimal fields); fetch full profile from DB
$current = get_logged_in_user();
$user = $current; // keep variable name used in template
$user_id = intval($current['id'] ?? 0);
if ($user_id) {
    $ust = $conn->prepare("SELECT id, username, email, full_name, gender, preferences, avatar, is_admin, points FROM users WHERE id = ? LIMIT 1");
    if ($ust) {
        $ust->bind_param('i', $user_id);
        $ust->execute();
        $resu = $ust->get_result();
        if ($resu && $resu->num_rows > 0) {
            $user = $resu->fetch_assoc();
        }
    }
}

// Wishlist count
$wishlist_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM wishlist WHERE user_id=$user_id"))['count'];

// Handle filters/search
$where = [];
$params = [];
$types = '';
if (!empty($_GET['q'])) {
    $q = '%'.$_GET['q'].'%';
    $where[] = "(name LIKE ? OR category LIKE ? OR description LIKE ?)";
    $params[] = $q; $params[] = $q; $params[] = $q; $types .= 'sss';
}
if (!empty($_GET['category'])) {
    $where[] = "category = ?";
    $params[] = $_GET['category']; $types .= 's';
}
if (isset($_GET['min_price']) && $_GET['min_price'] !== '') {
    $where[] = "price >= ?";
    $params[] = floatval($_GET['min_price']); $types .= 'd';
}
if (isset($_GET['max_price']) && $_GET['max_price'] !== '') {
    $where[] = "price <= ?";
    $params[] = floatval($_GET['max_price']); $types .= 'd';
}

if (count($where) > 0) {
    $sql = "SELECT * FROM items WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $bind_names[] = $types;
        for ($i=0; $i<count($params); $i++) {
            $bind_names[] = &$params[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    }
    $stmt->execute();
    $items = $stmt->get_result();
} else {
    $items = mysqli_query($conn, "SELECT * FROM items ORDER BY created_at DESC");
}

// Simple suggestions based on user preferences and top-rated items
$suggestions = [];
$prefs_raw = $current['preferences'] ?? '';
if (!empty($prefs_raw)) {
    $tokens = array_filter(array_map('trim', preg_split('/[,;\s]+/', $prefs_raw)));
    if (count($tokens) > 0) {
        $like = '%'.implode('%', $tokens).'%';
        $sstmt = $conn->prepare("SELECT * FROM items WHERE (name LIKE ? OR category LIKE ? OR description LIKE ?) LIMIT 6");
        $sstmt->bind_param('sss', $like, $like, $like);
        $sstmt->execute();
        $suggestions = $sstmt->get_result();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>FashionDB - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: 0.3s; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
        <span class="navbar-brand">👗 FashionDB</span>
        <div>
            <?php $curr = get_logged_in_user(); ?>
            <span class="text-white me-3">Welcome, <?php echo htmlspecialchars($curr['username'] ?? $_SESSION['username']); ?> (Points: <?php echo intval($curr['points'] ?? 0); ?>)</span>
            <a href="profile.php" class="btn btn-outline-light btn-sm me-2">Profile</a>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row">
        <!-- Profile Section -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Your Profile</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name'] ?? 'Not set'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Style Preference:</strong> <?php echo htmlspecialchars($user['preferences'] ?? 'Not set'); ?></p>
                    <p><strong>Wishlist Items:</strong> <?php echo $wishlist_count; ?></p>
                    <a href="wishlist.php" class="btn btn-outline-primary btn-sm w-100">View Wishlist ❤️</a>
                    <?php if (!empty($user['is_admin'])): ?>
                        <a href="add_item.php" class="btn btn-success btn-sm w-100 mt-2">+ Add Item</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Items Catalog -->
        <div class="col-md-8">
            <h3 class="mb-4">Explore Fashion Items</h3>

            <form method="get" class="row g-2 mb-3">
                <div class="col-sm-5"><input name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" class="form-control" placeholder="Search by name, category or description"></div>
                <div class="col-sm-3"><input name="min_price" value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>" class="form-control" placeholder="Min price"></div>
                <div class="col-sm-3"><input name="max_price" value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>" class="form-control" placeholder="Max price"></div>
                <div class="col-sm-1"><button class="btn btn-primary w-100">Go</button></div>
            </form>

            <?php
            // Use engine suggestions if available
            include_once 'engine.php';
            $suggest_list = [];
            if (function_exists('get_suggestions_for_user')) {
                $suggest_list = get_suggestions_for_user($user_id, 6);
            }
            if (!empty($suggest_list)):
            ?>
                <h5>Suggestions For You</h5>
                <div class="row mb-3">
                    <?php foreach($suggest_list as $s): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card p-2 text-center">
                                <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                                <div class="text-muted"><?php echo htmlspecialchars($s['category']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <?php while($item = mysqli_fetch_assoc($items)): 
                    // Check if item is already in wishlist
                    $check = mysqli_query($conn, "SELECT * FROM wishlist WHERE user_id=$user_id AND item_id=".$item['id']);
                    $in_wish = mysqli_num_rows($check) > 0;
                ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100 text-center">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                            <?php
                                $avgRes = mysqli_query($conn, "SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM ratings WHERE item_id=" . intval($item['id']));
                                $avgRow = $avgRes ? mysqli_fetch_assoc($avgRes) : null;
                                $avg = $avgRow && $avgRow['avg_rating'] ? round(floatval($avgRow['avg_rating']),1) : null;
                                $cnt = $avgRow ? intval($avgRow['cnt']) : 0;
                            ?>
                            <?php if ($avg !== null): ?>
                                <div class="mb-2">
                                    <small class="text-warning">
                                        <?php
                                            $full = floor($avg);
                                            for ($s=0;$s<$full;$s++) echo '★';
                                            for ($s=$full;$s<5;$s++) echo '☆';
                                        ?>
                                    </small>
                                    <div class="small text-muted"><?php echo $avg; ?> (<?php echo $cnt; ?>)</div>
                                </div>
                            <?php else: ?>
                                <div class="mb-2"><small class="text-muted">No ratings yet</small></div>
                            <?php endif; ?>
                            <p class="text-muted"><?php echo htmlspecialchars($item['category']); ?></p>
                            <?php
                                $img = '';
                                if (!empty($item['image'])) $img = $item['image'];
                                elseif (!empty($item['image_url'])) $img = $item['image_url'];
                                elseif (!empty($item['image_path'])) $img = $item['image_path'];
                                else $img = '';

                                // If it's a local uploads path, ensure file exists; otherwise show placeholder
                                if ($img && strpos($img, 'uploads/') === 0) {
                                    $local = __DIR__ . '/' . $img;
                                    if (!file_exists($local)) {
                                        $img = '';
                                    }
                                }
                                if (empty($img)) $img = 'https://via.placeholder.com/220x150?text=No+Image';
                            ?>
                            <div class="mb-3" style="flex:0 0 auto;">
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width:220px; height:150px; object-fit:cover;" onerror="if(this.src.indexOf('via.placeholder.com')===-1) this.src='https://via.placeholder.com/220x150?text=No+Image';">
                            </div>
                            
                            <div class="d-inline-flex align-items-center mt-auto" style="gap:8px; flex-wrap:wrap;">
                            <?php if($in_wish): ?>
                                <form method="post" action="wishlist.php" class="m-0">
                                    <?php echo csrf_input_field(); ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button class="btn btn-danger btn-sm" onclick="return confirm('Remove from wishlist?')">Remove from Wishlist</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="wishlist.php" class="m-0">
                                    <?php echo csrf_input_field(); ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button class="btn btn-success btn-sm">Add to Wishlist</button>
                                </form>
                            <?php endif; ?>

                            <?php if (!empty($user['is_admin'])): ?>
                                <!-- Admin-only: Add / Remove picture -->
                                <form method="post" action="upload_item_image.php" enctype="multipart/form-data" class="m-0">
                                    <?php echo csrf_input_field(); ?>
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <input type="file" name="image" accept="image/*" id="file_item_<?php echo $item['id']; ?>" style="display:none;" onchange="uploadItemImage(this)">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('file_item_<?php echo $item['id']; ?>').click();">Add Picture</button>
                                </form>
                                <?php if ($img && strpos($img, 'uploads/') === 0): ?>
                                    <form method="post" action="remove_item_image.php" class="m-0">
                                        <?php echo csrf_input_field(); ?>
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this picture?')">Remove Picture</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Place Order flow: show payment options after click -->
                            <div class="m-0 d-inline-flex" style="gap:6px;align-items:center;">
                                <button class="btn btn-primary btn-sm place-order-btn" data-item-id="<?php echo $item['id']; ?>" data-total="<?php echo htmlspecialchars($item['price']); ?>" data-name="<?php echo htmlspecialchars($item['name']); ?>">Place Order</button>
                            </div>
                            </div>
                            <form method="post" action="rate.php" class="mt-2">
                                <?php echo csrf_input_field(); ?>
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <div class="input-group input-group-sm">
                                    <select name="rating" class="form-select">
                                        <option value="">Rate</option>
                                        <option value="5">5</option>
                                        <option value="4">4</option>
                                        <option value="3">3</option>
                                        <option value="2">2</option>
                                        <option value="1">1</option>
                                    </select>
                                    <button class="btn btn-outline-secondary">OK</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    </div>
</div>

<script>
function uploadItemImage(inputElem) {
    if (!inputElem || !inputElem.closest) return;
    var form = inputElem.closest('form');
    if (!form) return;
    // --- preview flow: show preview and upload/cancel actions ---
    var file = inputElem.files && inputElem.files[0];
    if (!file) return;
    var card = inputElem.closest('.card');
    if (!card) return;
    // remove existing preview area if present
    var existing = card.querySelector('.upload-preview-area');
    if (existing) existing.remove();

    var previewArea = document.createElement('div');
    previewArea.className = 'upload-preview-area mt-2';
    previewArea.style.display = 'inline-flex';
    previewArea.style.alignItems = 'center';
    previewArea.style.gap = '8px';
    previewArea.style.flexWrap = 'wrap';

    var imgPreview = document.createElement('img');
    imgPreview.style.width = '80px';
    imgPreview.style.height = '60px';
    imgPreview.style.objectFit = 'cover';
    imgPreview.src = URL.createObjectURL(file);
    previewArea.appendChild(imgPreview);

    var uploadBtn = document.createElement('button');
    uploadBtn.className = 'btn btn-sm btn-primary';
    uploadBtn.textContent = 'Upload';
    var cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn btn-sm btn-outline-secondary';
    cancelBtn.textContent = 'Cancel';

    var status = document.createElement('div');
    status.style.minWidth = '120px';
    previewArea.appendChild(status);
    previewArea.appendChild(uploadBtn);
    previewArea.appendChild(cancelBtn);

    // append preview under card-body (so buttons remain in line)
    var body = card.querySelector('.card-body') || card;
    body.appendChild(previewArea);

    cancelBtn.addEventListener('click', function(e){
        e.preventDefault();
        if (previewArea) previewArea.remove();
        try { URL.revokeObjectURL(imgPreview.src); } catch(e){}
        inputElem.value = '';
    });

    uploadBtn.addEventListener('click', function(e){
        e.preventDefault();
        status.textContent = 'Uploading...';
        uploadBtn.disabled = true; cancelBtn.disabled = true;
        // build explicit FormData and append file/token/item
        var fd = new FormData();
        fd.append('image', file, file.name);
        var itemInput = form.querySelector('input[name="item_id"]');
        if (itemInput) fd.append('item_id', itemInput.value);
        var t = form.querySelector('input[name="csrf_token"]');
        if (t && t.value) fd.append('csrf_token', t.value);
        fetch(form.action, { method: 'POST', body: fd, credentials: 'include' })
            .then(function(res){
                return res.text().then(function(text){
                    // try to parse JSON even on non-2xx responses so we can show server messages
                    try {
                        var js = JSON.parse(text);
                        if (!res.ok) throw js;
                        return js;
                    } catch (e) {
                        // if parsing failed, rethrow a readable error
                        if (!res.ok) throw { error: 'server_error', detail: text };
                        throw e;
                    }
                });
            })
            .then(function(js){
                if (js && js.success) {
                    status.innerHTML = '<span class="text-success">Uploaded ✓</span>';
                    // update card image in place of product image; prefer original file if provided
                    var img = card.querySelector('img');
                    if (img) {
                        var newUrl = (js.orig ? js.orig : js.url) + '?_=' + Date.now();
                        var tmp = new Image();
                        tmp.onload = function(){ img.src = newUrl; try{ URL.revokeObjectURL(imgPreview.src); } catch(e){} };
                        tmp.onerror = function(){ img.src = js.url + '?_=' + Date.now(); };
                        tmp.src = newUrl;
                    }
                    setTimeout(function(){ if (previewArea) previewArea.remove(); }, 1200);
                } else {
                    var msg = (js && js.error) ? js.error : 'Upload failed';
                    status.innerHTML = '<span class="text-danger">' + msg + '</span>';
                    console.error(js && js.detail ? js.detail : js);
                    uploadBtn.disabled = false; cancelBtn.disabled = false;
                }
            }).catch(function(err){
                var msg = 'Upload error';
                if (err && typeof err === 'object') msg = err.error || err.detail || err.message || JSON.stringify(err);
                else if (typeof err === 'string') msg = err;
                status.innerHTML = '<span class="text-danger">' + msg + '</span>';
                console.error(err);
                uploadBtn.disabled = false; cancelBtn.disabled = false;
            });
    });
}
</script>

<!-- Payment modal -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="payModalTitle">Pay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <input type="hidden" id="pay_item_id" value="">
                <input type="hidden" id="pay_total" value="">
                <?php echo csrf_input_field(); ?>
                <input type="hidden" id="pay_csrf" value="<?php $t=generate_csrf_token(); echo htmlspecialchars($t); ?>">
                <div class="d-grid gap-2">
                    <button id="pay_ssl" class="btn btn-primary">SSLCommerz</button>
                    <button id="pay_stripe" class="btn btn-secondary">Stripe</button>
                    <button id="pay_cod" class="btn btn-outline-dark">Cash on Delivery</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Payment options flow
// Use modal for payment options
document.addEventListener('click', function(e){
    var t = e.target;
    if (t.classList.contains('place-order-btn')) {
        var itemId = t.getAttribute('data-item-id');
        var total = t.getAttribute('data-total');
        var name = t.getAttribute('data-name');
        // populate modal
        document.getElementById('payModalTitle').textContent = name + ' — ' + total;
        document.getElementById('pay_item_id').value = itemId;
        document.getElementById('pay_total').value = total;
        // show modal
        var m = new bootstrap.Modal(document.getElementById('payModal'));
        m.show();
        return;
    }
});

// handle modal payment button clicks
document.getElementById('pay_ssl').addEventListener('click', function(e){ doPayment('SSLCommerz'); });
document.getElementById('pay_stripe').addEventListener('click', function(e){ doPayment('stripe'); });
document.getElementById('pay_cod').addEventListener('click', function(e){ doPayment('cod'); });

function doPayment(method) {
    var itemId = document.getElementById('pay_item_id').value;
    var total = document.getElementById('pay_total').value;
    var csrf = document.getElementById('pay_csrf') ? document.getElementById('pay_csrf').value : '';
    var fd = new FormData();
    fd.append('payment_method', method);
    fd.append('item_id', itemId);
    fd.append('total', total);
    fd.append('csrf_token', csrf);
    fd.append('ajax', '1');
    var btn = document.getElementById('pay_' + (method==='cod'?'cod':method.toLowerCase()));
    btn.disabled = true;
    fetch('payment/initiate.php', { method: 'POST', body: fd, credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(function(js){
            if (js.redirect) { window.location = js.redirect; return; }
            if (js.success) {
                var msg = (js.method==='cod' || method==='cod') ? 'Order placed (Cash on Delivery)' : 'Order placed';
                alert(msg);
                var m = bootstrap.Modal.getInstance(document.getElementById('payModal'));
                if (m) m.hide();
                btn.disabled = false;
                return;
            }
            alert('Payment error: ' + (js.error || js.detail || 'unknown'));
            btn.disabled = false;
        }).catch(function(err){ console.error(err); alert('Payment request failed'); btn.disabled = false; });
}
</script>

</body>
</html>