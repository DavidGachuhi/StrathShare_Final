// GLOBAL STATE & CONFIGURATION

let currentUser = null;
let currentView = 'discover';
let skills = [];
let conversations = [];
let currentChatUser = null;
let unreadCounts = { messages: 0, notifications: 0 };
let messageRefreshInterval = null;

const API_BASE = 'api/';

// INITIALIZATION

document.addEventListener('DOMContentLoaded', () => {
    console.log('StrathShare initializing...');
    checkAuth();
});

function checkAuth() {
    const user = localStorage.getItem('currentUser');
    
    if (!user) {
        console.log('No user found, redirecting to login...');
        window.location.href = 'auth-portal.html';
        return;
    }
    
    currentUser = JSON.parse(user);
    console.log('User authenticated:', currentUser.first_name || currentUser.fname);
    
    // Check if admin trying to access student dashboard
    if (currentUser.is_admin) {
        window.location.href = 'admin-dashboard.html';
        return;
    }
    
    initializeApp();
}

function initializeApp() {
    console.log('Initializing app...');
    loadSkills();
    setupEventListeners();
    updateUserInfo();
    renderView('discover');
    
    // Start polling for unread counts
    loadUnreadCounts();
    setInterval(loadUnreadCounts, 30000); // Check every 30 seconds
}

function setupEventListeners() {
    // Tab navigation
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', (e) => {
            const view = e.currentTarget.dataset.view;
            renderView(view);
        });
    });
    
    // Logout button
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logout);


    }
}

function updateUserInfo() {
    const nameEl = document.getElementById('userName');
    const emailEl = document.getElementById('userEmail');
    const avatarEl = document.getElementById('userAvatar');
    
    const firstName = currentUser.first_name || currentUser.fname || '';
    const lastName = currentUser.last_name || currentUser.lname || '';
    const email = currentUser.user_email || currentUser.email || '';
    
    if (nameEl) nameEl.textContent = `${firstName} ${lastName}`;
    if (emailEl) emailEl.textContent = email;
    
    if (avatarEl && currentUser.profile_picture_url) {
        avatarEl.src = currentUser.profile_picture_url;
    }
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        localStorage.removeItem('currentUser');
        window.location.href = 'auth-portal.html';
    }
}

// =====================================================
// LOAD UNREAD COUNTS (for badges)
// =====================================================
async function loadUnreadCounts() {
    const userId = currentUser.user_id || currentUser.id;
    if (!userId) return;
    
    try {
        const response = await fetch(`${API_BASE}get_unread_counts.php?user_id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            unreadCounts.messages = data.unread_messages;
            unreadCounts.notifications = data.unread_notifications;
            updateBadges();
        }
    } catch (error) {
        console.error('Error loading unread counts:', error);
    }
}

function updateBadges() {
    // Update messages tab badge
    const messagesTab = document.querySelector('[data-view="messages"]');
    if (messagesTab) {
        let badge = messagesTab.querySelector('.badge');
        if (unreadCounts.messages > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge';
                messagesTab.appendChild(badge);
            }
            badge.textContent = unreadCounts.messages > 99 ? '99+' : unreadCounts.messages;
        } else if (badge) {
            badge.remove();
        }
    }
}

// =====================================================
// LOAD SKILLS FROM DATABASE
// =====================================================
async function loadSkills() {
    try {
        const response = await fetch(`${API_BASE}get_skills.php`);
        const data = await response.json();
        
        if (data.success) {
            skills = data.skills;
            console.log(`Loaded ${skills.length} skills`);
        } else {
            console.error('Failed to load skills');
        }
    } catch (error) {
        console.error('Error loading skills:', error);
    }
}

// =====================================================
// VIEW NAVIGATION
// =====================================================
function renderView(viewName) {
    currentView = viewName;
    console.log('Rendering view:', viewName);
    
    // Clear any message refresh intervals
    if (messageRefreshInterval) {
        clearInterval(messageRefreshInterval);
        messageRefreshInterval = null;
    }
    
    // Update active tab
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.dataset.view === viewName) {
            tab.classList.add('active');
        }
    });
    
    // Get main content area
    const mainContent = document.getElementById('mainContent');
    if (!mainContent) {
        console.error('Main content area not found');
        return;
    }
    
    // Render appropriate view
    if (viewName === 'discover') renderDiscover();
    else if (viewName === 'browse') renderBrowse();
    else if (viewName === 'post') renderPostGig();
    else if (viewName === 'request') renderRequest();
    else if (viewName === 'messages') renderMessages();
    else if (viewName === 'profile') renderProfile();
    else if (viewName === 'settings') renderSettings();
}

// =====================================================
// VIEW: DISCOVER (Trending/Featured Services)
// =====================================================
async function renderDiscover() {
    const mainContent = document.getElementById('mainContent');
    const userId = currentUser.user_id || currentUser.id;
    
    mainContent.innerHTML = `
        <div class="view-header">
            <h2>🔥 Discover Services</h2>
            <p>Top-rated services from fellow students</p>
        </div>
        
        <!-- My Active Requests Section -->
        <div class="card" id="activeRequestsSection" style="display:none;">
            <h3>📋 My Active Requests</h3>
            <div id="activeRequestsList"></div>
        </div>
        
        <!-- Open Requests I Can Accept (as provider) -->
        <div class="card" id="openRequestsSection">
            <h3>🙋 Open Requests - Help Needed!</h3>
            <div id="openRequestsList" class="loading">Loading requests...</div>
        </div>
        
        <!-- Services -->
        <div class="card">
            <h3>💼 Featured Services</h3>
            <div id="discoverContent" class="grid">
                <div class="loading">Loading services...</div>
            </div>
        </div>
    `;
    
    // Load my active requests (as seeker or provider)
    loadMyActiveRequests();
    
    // Load open requests (that I can accept as provider)
    loadOpenRequests();
    
    // Load featured services
    try {
        const response = await fetch(`${API_BASE}browse_services.php`);
        const data = await response.json();
        
        if (data.success && data.services.length > 0) {
            const discoverContent = document.getElementById('discoverContent');
            
            // Show top 6 services sorted by rating
            const topServices = data.services
                .sort((a, b) => b.average_rating - a.average_rating)
                .slice(0, 6);
            
            discoverContent.innerHTML = topServices.map(service => createServiceCard(service)).join('');
        } else {
            document.getElementById('discoverContent').innerHTML = `
                <div class="empty-state">No services available yet. Be the first to offer your skills!</div>
            `;
        }
    } catch (error) {
        console.error('Error loading services:', error);
        document.getElementById('discoverContent').innerHTML = `
            <div class="error-state">Failed to load services. Please try again.</div>
        `;
    }
}

async function loadMyActiveRequests() {
    const userId = currentUser.user_id || currentUser.id;
    
    try {
        const response = await fetch(`${API_BASE}get_my_requests.php?user_id=${userId}&role=all`);
        const data = await response.json();
        
        if (data.success) {
            // Filter to show only active (not completed/cancelled) requests
            const activeRequests = data.requests.filter(r => 
                !['completed', 'cancelled'].includes(r.status)
            );
            
            if (activeRequests.length > 0) {
                document.getElementById('activeRequestsSection').style.display = 'block';
                document.getElementById('activeRequestsList').innerHTML = activeRequests.map(request => {
                    const isSeeker = request.seeker_id == userId;
                    return createActiveRequestCard(request, isSeeker);
                }).join('');
            }
        }
    } catch (error) {
        console.error('Error loading active requests:', error);
    }
}

async function loadOpenRequests() {
    const userId = currentUser.user_id || currentUser.id;
    const openRequestsList = document.getElementById('openRequestsList');
    
    if (!openRequestsList) return;
    
    try {
        const response = await fetch(`${API_BASE}get_requests.php`);
        const data = await response.json();
        
        if (data.success && data.requests && data.requests.length > 0) {
            // Filter out user's own requests
            const otherRequests = data.requests.filter(r => r.seeker_id != userId && r.user_id != userId);
            
            if (otherRequests.length > 0) {
                openRequestsList.innerHTML = otherRequests.slice(0, 5).map(request => `
                    <div class="request-card">
                        <div class="request-header">
                            <h4>${escapeHtml(request.title)}</h4>
                            <span class="skill-badge">${escapeHtml(request.skill_name)}</span>
                        </div>
                        <p>${escapeHtml(request.description).substring(0, 100)}...</p>
                        <div class="request-footer">
                            <span>Budget: KES ${request.budget || 'Negotiable'}</span>
                            <span>By: ${escapeHtml(request.first_name)} ${escapeHtml(request.last_name)}</span>
                            <button onclick="acceptRequest(${request.request_id})" class="btn-primary btn-small">Accept Request</button>
                        </div>
                    </div>
                `).join('');
            } else {
                openRequestsList.innerHTML = `
                    <div class="empty-state" style="padding: 30px; text-align: center; color: var(--text-muted);">
                        <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                        <p>No open requests from other students right now.</p>
                        <p class="small">Check back later or browse services instead!</p>
                    </div>
                `;
            }
        } else {
            openRequestsList.innerHTML = `
                <div class="empty-state" style="padding: 30px; text-align: center; color: var(--text-muted);">
                    <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                    <p>No open requests yet.</p>
                    <p class="small">Be the first to request help!</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading open requests:', error);
        openRequestsList.innerHTML = `
            <div class="error-state" style="padding: 20px; text-align: center; color: #ff6b6b;">
                <p>⚠️ Failed to load requests. Please refresh the page.</p>
            </div>
        `;
    }
}

function createServiceCard(service) {
    return `
        <div class="gig" onclick="viewServiceDetails(${service.listing_id})">
            <div class="service-header">
                <h4>${escapeHtml(service.title)}</h4>
                <span class="skill-badge">${escapeHtml(service.skill_name)}</span>
            </div>
            <p class="muted">${escapeHtml(service.description).substring(0, 80)}...</p>
            <div class="meta">
                <div class="row">
                    <span class="small">${escapeHtml(service.first_name)} ${escapeHtml(service.last_name)}</span>
                </div>
                <div>
                    <span class="rating">${'★'.repeat(Math.round(service.average_rating))}${'☆'.repeat(5 - Math.round(service.average_rating))}</span>
                    <span class="small">(${service.total_reviews})</span>
                </div>
            </div>
            <div class="price-tag">${service.price_range || 'Contact for price'}</div>
        </div>
    `;
}

function createActiveRequestCard(request, isSeeker) {
    const statusColors = {
        'open': '#ffc107',
        'assigned': '#17a2b8',
        'in_progress': '#007bff',
        'awaiting_payment': '#ff2d55',
        'completed': '#28a745',
        'cancelled': '#6c757d'
    };
    
    let actionButtons = '';
    const userId = currentUser.user_id || currentUser.id;
    
    if (isSeeker) {
        // Seeker actions
        if (request.status === 'awaiting_payment') {
            actionButtons = `
                <button onclick="showPaymentModal(${request.request_id}, ${request.budget || 0}, ${request.provider_id})" class="btn-primary">
                    💰 Confirm & Pay
                </button>
            `;
        }
    } else {
        // Provider actions
        if (request.status === 'assigned') {
            actionButtons = `
                <button onclick="startWork(${request.request_id})" class="btn-secondary">▶️ Start Work</button>
                <button onclick="markComplete(${request.request_id})" class="btn-primary">✅ Mark Complete</button>
            `;
        } else if (request.status === 'in_progress') {
            actionButtons = `
                <button onclick="markComplete(${request.request_id})" class="btn-primary">✅ Mark Complete</button>
            `;
        }
    }
    
    return `
        <div class="request-item" style="border-left: 4px solid ${statusColors[request.status]}; padding: 15px; margin-bottom: 15px; background: rgba(255,255,255,0.02); border-radius: 8px;">
            <div class="request-item-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="margin: 0;">${escapeHtml(request.title)}</h4>
                <span class="status-badge ${request.status}" style="padding: 4px 12px; border-radius: 20px; font-size: 12px; text-transform: uppercase;">${request.status.replace('_', ' ')}</span>
            </div>
            <p class="muted" style="margin: 10px 0;">${escapeHtml(request.description).substring(0, 100)}...</p>
            <div class="request-item-meta" style="display: flex; flex-wrap: wrap; gap: 15px; margin: 10px 0; font-size: 14px;">
                <span>🏷️ ${request.skill_name}</span>
                <span>💰 KES ${request.budget || 'Negotiable'}</span>
            </div>
            <div class="request-item-contact" style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; margin: 10px 0;">
                <p style="margin: 0; font-size: 14px;">
                    ${isSeeker ? '👤 Provider' : '👤 Seeker'}: 
                    <strong>${isSeeker ? 
                        (request.provider_first_name ? request.provider_first_name + ' ' + request.provider_last_name : 'Waiting for provider...') : 
                        request.seeker_first_name + ' ' + request.seeker_last_name
                    }</strong>
                </p>
                ${(!isSeeker && request.seeker_email) || (isSeeker && request.provider_email) ? `
                    <p style="margin: 5px 0 0; font-size: 13px;">
                        📧 <a href="mailto:${isSeeker ? request.provider_email : request.seeker_email}" style="color: var(--pink);">
                            ${isSeeker ? request.provider_email : request.seeker_email}
                        </a>
                    </p>
                ` : ''}
            </div>
            <div class="request-actions" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                ${actionButtons}
            </div>
        </div>
    `;
}

// =====================================================
// REQUEST WORKFLOW FUNCTIONS
// =====================================================

async function acceptRequest(requestId) {
    const userId = currentUser.user_id || currentUser.id;
    
    if (!confirm('Accept this request? You will be assigned as the provider.')) return;
    
    try {
        const response = await fetch(`${API_BASE}accept_request.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: requestId,
                provider_id: userId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Request accepted! You can now start working on it.');
            renderView('discover');
        } else {
            alert('Failed to accept request: ' + data.message);
        }
    } catch (error) {
        console.error('Error accepting request:', error);
        alert('Failed to accept request. Please try again.');
    }
}

async function startWork(requestId) {
    const userId = currentUser.user_id || currentUser.id;
    
    try {
        const response = await fetch(`${API_BASE}start_work.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: requestId,
                provider_id: userId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('▶️ Work started! The seeker has been notified.');
            renderView('discover');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error starting work:', error);
        alert('Failed to start work. Please try again.');
    }
}

async function markComplete(requestId) {
    const userId = currentUser.user_id || currentUser.id;
    
    if (!confirm('Mark this request as complete? The seeker will be asked to confirm and pay.')) return;
    
    try {
        const response = await fetch(`${API_BASE}mark_complete.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: requestId,
                provider_id: userId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Request marked as complete! Waiting for seeker to confirm and pay.');
            renderView('discover');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error marking complete:', error);
        alert('Failed to mark as complete. Please try again.');
    }
}

// =====================================================
// M-PESA PAYMENT MODAL
// =====================================================

function showPaymentModal(requestId, amount, providerId) {
    const userPhone = currentUser.phone_number || currentUser.phone || '';
    
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'paymentModal';
    modal.innerHTML = `
        <div class="modal-content payment-modal">
            <button class="modal-close" onclick="closePaymentModal()">×</button>
            
            <div class="payment-header">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/15/M-PESA_LOGO-01.svg/1200px-M-PESA_LOGO-01.svg.png" 
                     alt="M-Pesa" style="height: 40px; margin-bottom: 15px;">
                <h2>Complete Payment</h2>
            </div>
            
            <div class="payment-amount">
                <span class="currency">KES</span>
                <span class="amount">${amount.toLocaleString()}</span>
            </div>
            
            <div class="form-group">
                <label>M-Pesa Phone Number</label>
                <input type="tel" id="mpesaPhone" value="${userPhone}" placeholder="254712345678" required>
                <small class="muted">Format: 254XXXXXXXXX (12 digits)</small>
            </div>
            
            <div id="paymentStatus" style="display:none;"></div>
            
            <button id="payButton" onclick="processPayment(${requestId}, ${amount}, ${providerId})" class="btn-primary" style="width:100%; margin-top: 15px;">
                Pay with M-Pesa
            </button>
            
            <p class="muted" style="text-align: center; margin-top: 15px; font-size: 12px;">
                <strong>DEMO MODE:</strong> No real money will be charged. Payment will auto-confirm in 10 seconds.
            </p>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function closePaymentModal() {
    const modal = document.getElementById('paymentModal');
    if (modal) modal.remove();
}

async function processPayment(requestId, amount, providerId) {
    const phone = document.getElementById('mpesaPhone').value.trim();
    const payButton = document.getElementById('payButton');
    const statusDiv = document.getElementById('paymentStatus');
    const userId = currentUser.user_id || currentUser.id;
    
    // Validate phone
    if (!/^254[0-9]{9}$/.test(phone)) {
        alert('Please enter a valid phone number in format: 254XXXXXXXXX');
        return;
    }
    
    // Disable button and show processing
    payButton.disabled = true;
    payButton.textContent = 'Processing...';
    statusDiv.style.display = 'block';
    statusDiv.innerHTML = `
        <div class="processing-animation">
            <div class="spinner"></div>
            <p>Sending STK Push to ${phone}...</p>
            <p class="muted">Check your phone for the M-Pesa prompt</p>
        </div>
    `;
    
    try {
        const response = await fetch(`${API_BASE}mpesa_payment.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                request_id: requestId,
                payer_id: userId,
                receiver_id: providerId,
                amount: amount,
                phone_number: phone
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success animation
            statusDiv.innerHTML = `
                <div class="success-animation">
                    <div class="checkmark">✅</div>
                    <h3>Payment Successful!</h3>
                    <p>M-Pesa Reference: <strong>${data.mpesa_receipt}</strong></p>
                    <p class="muted">Amount: KES ${amount.toLocaleString()}</p>
                </div>
            `;
            
            payButton.style.display = 'none';
            
            // Show review prompt after 3 seconds
            setTimeout(() => {
                statusDiv.innerHTML += `
                    <div style="margin-top: 20px;">
                        <p>Don't forget to rate your provider!</p>
                        <button onclick="closePaymentModal(); showReviewModal(${data.transaction_id}, ${providerId})" class="btn-primary">
                            ⭐ Leave Review
                        </button>
                        <button onclick="closePaymentModal(); renderView('discover')" class="btn-secondary" style="margin-left: 10px;">
                            Skip for Now
                        </button>
                    </div>
                `;
            }, 2000);
            
        } else {
            statusDiv.innerHTML = `
                <div class="error-animation">
                    <div class="error-icon">❌</div>
                    <h3>Payment Failed</h3>
                    <p>${data.message}</p>
                </div>
            `;
            payButton.disabled = false;
            payButton.textContent = 'Try Again';
        }
        
    } catch (error) {
        console.error('Payment error:', error);
        statusDiv.innerHTML = `
            <div class="error-animation">
                <div class="error-icon">❌</div>
                <h3>Payment Error</h3>
                <p>Something went wrong. Please try again.</p>
            </div>
        `;
        payButton.disabled = false;
        payButton.textContent = 'Try Again';
    }
}

// =====================================================
// REVIEW MODAL
// =====================================================

function showReviewModal(transactionId, revieweeId) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'reviewModal';
    modal.innerHTML = `
        <div class="modal-content review-modal">
            <button class="modal-close" onclick="closeReviewModal()">×</button>
            
            <h2>⭐ Leave a Review</h2>
            <p class="muted">How was your experience?</p>
            
            <div class="star-rating" id="starRating">
                <span class="star" data-rating="1">☆</span>
                <span class="star" data-rating="2">☆</span>
                <span class="star" data-rating="3">☆</span>
                <span class="star" data-rating="4">☆</span>
                <span class="star" data-rating="5">☆</span>
            </div>
            <input type="hidden" id="selectedRating" value="0">
            
            <div class="form-group">
                <label>Comment (optional)</label>
                <textarea id="reviewComment" rows="3" placeholder="Share your experience..."></textarea>
            </div>
            
            <button onclick="submitReview(${transactionId}, ${revieweeId})" class="btn-primary" style="width:100%;">
                Submit Review
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Star rating interaction
    const stars = document.querySelectorAll('#starRating .star');
    stars.forEach(star => {
        star.addEventListener('click', () => {
            const rating = parseInt(star.dataset.rating);
            document.getElementById('selectedRating').value = rating;
            
            stars.forEach((s, i) => {
                s.textContent = i < rating ? '★' : '☆';
                s.classList.toggle('selected', i < rating);
            });
        });
        
        star.addEventListener('mouseover', () => {
            const rating = parseInt(star.dataset.rating);
            stars.forEach((s, i) => {
                s.textContent = i < rating ? '★' : '☆';
            });
        });
        
        star.addEventListener('mouseout', () => {
            const currentRating = parseInt(document.getElementById('selectedRating').value);
            stars.forEach((s, i) => {
                s.textContent = i < currentRating ? '★' : '☆';
            });
        });
    });
}

function closeReviewModal() {
    const modal = document.getElementById('reviewModal');
    if (modal) modal.remove();
}

async function submitReview(transactionId, revieweeId) {
    const rating = parseInt(document.getElementById('selectedRating').value);
    const comment = document.getElementById('reviewComment').value.trim();
    const userId = currentUser.user_id || currentUser.id;
    
    if (rating < 1 || rating > 5) {
        alert('Please select a rating (1-5 stars)');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}submit_review.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                transaction_id: transactionId,
                reviewer_id: userId,
                reviewee_id: revieweeId,
                rating: rating,
                comment: comment
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Thank you for your review!');
            closeReviewModal();
            renderView('discover');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error submitting review:', error);
        alert('Failed to submit review. Please try again.');
    }
}

// =====================================================
// VIEW: BROWSE (All Services with Search/Filter)
// =====================================================
async function renderBrowse() {
    const mainContent = document.getElementById('mainContent');
    
    mainContent.innerHTML = `
        <div class="view-header">
            <h2>📚 Browse All Services</h2>
        </div>
        
        <div class="card">
            <div class="search-bar" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="text" id="searchInput" placeholder="Search services..." style="flex: 1; min-width: 200px;">
                <select id="skillFilter" style="min-width: 150px;">
                    <option value="">All Skills</option>
                </select>
                <button onclick="searchServices()" class="btn-primary">Search</button>
                <button onclick="clearSearch()" class="btn-ghost">Clear</button>
            </div>
        </div>
        
        <div id="browseContent" class="grid">
            <div class="loading">Loading services...</div>
        </div>
    `;
    
    // Populate skill filter
    const skillFilter = document.getElementById('skillFilter');
    const categories = [...new Set(skills.map(s => s.category))];
    categories.forEach(category => {
        const optgroup = document.createElement('optgroup');
        optgroup.label = category;
        skills.filter(s => s.category === category).forEach(skill => {
            const option = document.createElement('option');
            option.value = skill.skill_id;
            option.textContent = skill.skill_name;
            optgroup.appendChild(option);
        });
        skillFilter.appendChild(optgroup);
    });
    
    // Enter key search
    document.getElementById('searchInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') searchServices();
    });
    
    await searchServices();
}

async function searchServices() {
    const searchInput = document.getElementById('searchInput').value;
    const skillFilter = document.getElementById('skillFilter').value;
    
    const browseContent = document.getElementById('browseContent');
    browseContent.innerHTML = '<div class="loading">Searching...</div>';
    
    try {
        let url = `${API_BASE}search_services.php?`;
        if (searchInput) url += `search=${encodeURIComponent(searchInput)}&`;
        if (skillFilter) url += `skill_id=${skillFilter}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success && data.services.length > 0) {
            browseContent.innerHTML = data.services.map(service => createServiceCard(service)).join('');
        } else {
            browseContent.innerHTML = `
                <div class="empty-state">No services found matching your search.</div>
            `;
        }
    } catch (error) {
        console.error('Error searching services:', error);
        browseContent.innerHTML = `<div class="error-state">Search failed. Please try again.</div>`;
    }
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('skillFilter').value = '';
    searchServices();
}

// =====================================================
// VIEW SERVICE DETAILS (Modal)
// =====================================================
async function viewServiceDetails(listingId) {
    try {
        const response = await fetch(`${API_BASE}get_service_details.php?listing_id=${listingId}`);
        const data = await response.json();
        
        if (data.success) {
            const service = data.service;
            const userId = currentUser.user_id || currentUser.id;
            
            showModal(`
                <div class="service-details">
                    <h2>${escapeHtml(service.title)}</h2>
                    <span class="skill-badge">${escapeHtml(service.skill_name)}</span>
                    
                    <div class="provider-card" style="margin: 20px 0; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div class="avatar-large">
                                ${service.profile_picture_url ? 
                                    `<img src="${service.profile_picture_url}" alt="${service.first_name}" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">` : 
                                    `<div style="width:60px;height:60px;border-radius:50%;background:var(--pink);display:flex;align-items:center;justify-content:center;font-size:20px;">${service.first_name[0]}${service.last_name[0]}</div>`
                                }
                            </div>
                            <div>
                                <h3 style="margin:0;">${escapeHtml(service.first_name)} ${escapeHtml(service.last_name)}</h3>
                                <div class="rating">
                                    ${'★'.repeat(Math.round(service.average_rating))}${'☆'.repeat(5 - Math.round(service.average_rating))}
                                    <span class="muted">${parseFloat(service.average_rating).toFixed(1)} (${service.total_reviews} reviews)</span>
                                </div>
                                <p class="muted" style="margin:5px 0 0;">${escapeHtml(service.bio || 'No bio available')}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin: 20px 0;">
                        <h4>Description</h4>
                        <p>${escapeHtml(service.description)}</p>
                    </div>
                    
                    <div style="margin: 20px 0;">
                        <h4>Pricing</h4>
                        <p style="font-size: 24px; color: var(--pink);">${service.price_range || 'Contact for price'}</p>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        ${service.user_id != userId ? 
                            `<button onclick="requestServiceHelp(${service.listing_id}, ${service.user_id}, '${escapeHtml(service.title).replace(/'/g, "\\'")}', '${escapeHtml(service.skill_name).replace(/'/g, "\\'")}', ${service.skill_id})" class="btn-primary">
                                🙋 Request This Service
                            </button>
                            <button onclick="openChat(${service.user_id}, '${escapeHtml(service.first_name)}')" class="btn-secondary">
                                💬 Message (Optional)
                            </button>` : 
                            `<button onclick="closeModal(); renderView('post')" class="btn-secondary">
                                ✏️ Edit Service
                            </button>`
                        }
                    </div>
                    
                    <div style="margin-top: 15px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                        <p class="small muted" style="margin: 0;">📧 Contact directly: <strong style="color: var(--pink);">${escapeHtml(service.provider_email || service.user_email || 'Email not available')}</strong></p>
                    </div>
                </div>
            `);
        }
    } catch (error) {
        console.error('Error loading service details:', error);
        alert('Failed to load service details');
    }
}

// Request help from a service provider - creates a request directly
async function requestServiceHelp(listingId, providerId, serviceTitle, skillName, skillId) {
    const userId = currentUser.user_id || currentUser.id;
    
    // Show confirmation modal
    showModal(`
        <div style="text-align: center; padding: 20px;">
            <h2>🙋 Request This Service</h2>
            <p style="margin: 20px 0;">You're about to request help with:</p>
            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; margin: 15px 0;">
                <h3 style="color: var(--pink); margin: 0;">${escapeHtml(serviceTitle)}</h3>
                <span class="skill-badge" style="margin-top: 10px; display: inline-block;">${escapeHtml(skillName)}</span>
            </div>
            
            <div class="form-group" style="text-align: left; margin: 20px 0;">
                <label>Your Budget (KES) - Optional</label>
                <input type="number" id="requestBudget" min="0" placeholder="e.g., 1000" style="width: 100%;">
            </div>
            
            <div class="form-group" style="text-align: left; margin: 20px 0;">
                <label>Brief Description of What You Need</label>
                <textarea id="requestDescription" rows="3" placeholder="Describe what you need help with..." style="width: 100%;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                <button onclick="closeModal()" class="btn-secondary">Cancel</button>
                <button onclick="submitServiceRequest(${listingId}, ${providerId}, '${escapeHtml(serviceTitle).replace(/'/g, "\\'")}', ${skillId})" class="btn-primary">
                    ✓ Submit Request
                </button>
            </div>
        </div>
    `);
}

// Submit the service request
async function submitServiceRequest(listingId, providerId, serviceTitle, skillId) {
    const userId = currentUser.user_id || currentUser.id;
    const budget = document.getElementById('requestBudget').value || null;
    const description = document.getElementById('requestDescription').value.trim() || `Requesting help with: ${serviceTitle}`;
    
    try {
        const response = await fetch(`${API_BASE}create_request.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                seeker_id: userId,
                skill_id: skillId,
                title: `Request: ${serviceTitle}`,
                description: description,
                budget: budget,
                provider_id: providerId  // Pre-assign to this provider
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModal();
            alert('✓ Request submitted successfully! The provider will be notified.');
            renderView('discover'); // Go to discover to see the request
        } else {
            alert('Failed to submit request: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error submitting request:', error);
        alert('Failed to submit request. Please try again.');
    }
}

// =====================================================
// VIEW: POST GIG (Create Service)
// =====================================================
function renderPostGig() {
    const mainContent = document.getElementById('mainContent');
    
    mainContent.innerHTML = `
        <div class="view-header">
            <h2>💼 Post Your Service</h2>
            <p>Offer your skills to fellow students</p>
        </div>
        
        <div class="card">
            <form id="postServiceForm" onsubmit="submitService(event)">
                <div class="form-group">
                    <label>Service Title *</label>
                    <input type="text" id="serviceTitle" required placeholder="e.g., Python Programming Tutoring">
                </div>
                
                <div class="form-group">
                    <label>Select Skill *</label>
                    <select id="serviceSkill" required>
                        <option value="">Choose a skill...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea id="serviceDescription" required rows="5" 
                        placeholder="Describe what you offer, your experience, and what students can expect..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Minimum Price (KES)</label>
                        <input type="number" id="servicePriceMin" min="0" placeholder="500">
                    </div>
                    <div class="form-group">
                        <label>Maximum Price (KES)</label>
                        <input type="number" id="servicePriceMax" min="0" placeholder="2000">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">Post Service</button>
                    <button type="button" onclick="renderView('discover')" class="btn-ghost">Cancel</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h3>My Posted Services</h3>
            <div id="myServicesList">
                <div class="loading">Loading your services...</div>
            </div>
        </div>
    `;
    
    // Populate skill dropdown
    const skillSelect = document.getElementById('serviceSkill');
    const categories = [...new Set(skills.map(s => s.category))];
    categories.forEach(category => {
        const optgroup = document.createElement('optgroup');
        optgroup.label = category;
        skills.filter(s => s.category === category).forEach(skill => {
            const option = document.createElement('option');
            option.value = skill.skill_id;
            option.textContent = skill.skill_name;
            optgroup.appendChild(option);
        });
        skillSelect.appendChild(optgroup);
    });
    
    loadMyServices();
}

async function submitService(event) {
    event.preventDefault();
    
    const title = document.getElementById('serviceTitle').value;
    const skillId = document.getElementById('serviceSkill').value;
    const description = document.getElementById('serviceDescription').value;
    const priceMin = document.getElementById('servicePriceMin').value;
    const priceMax = document.getElementById('servicePriceMax').value;
    const userId = currentUser.user_id || currentUser.id;
    
    try {
        const response = await fetch(`${API_BASE}create_service.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                provider_id: userId,
                skill_id: parseInt(skillId),
                title: title,
                description: description,
                price_min: priceMin ? parseFloat(priceMin) : null,
                price_max: priceMax ? parseFloat(priceMax) : null
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Service posted successfully!');
            document.getElementById('postServiceForm').reset();
            loadMyServices();
        } else {
            alert('Failed to post service: ' + data.message);
        }
    } catch (error) {
        console.error('Error posting service:', error);
        alert('Failed to post service. Please try again.');
    }
}

async function loadMyServices() {
    const myServicesList = document.getElementById('myServicesList');
    if (!myServicesList) return;
    
    const userId = currentUser.user_id || currentUser.id;
    
    try {
        const response = await fetch(`${API_BASE}get_my_services.php?provider_id=${userId}`);
        const data = await response.json();
        
        if (data.success && data.services.length > 0) {
            myServicesList.innerHTML = data.services.map(service => `
                <div class="service-item" style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h4 style="margin: 0;">${escapeHtml(service.title)}</h4>
                            <span class="skill-badge">${escapeHtml(service.skill_name)}</span>
                            <p class="muted" style="margin: 5px 0;">${escapeHtml(service.description).substring(0, 100)}...</p>
                            <span class="muted">${service.price_range || 'No price set'}</span>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <span class="status-badge ${service.availability ? 'active' : 'paused'}">
                                ${service.availability ? 'Available' : 'Paused'}
                            </span>
                            <button onclick="deleteService(${service.listing_id})" class="btn-ghost btn-small" style="color: #ff4444;">🗑️</button>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            myServicesList.innerHTML = `<div class="empty-state">You haven't posted any services yet.</div>`;
        }
    } catch (error) {
        console.error('Error loading services:', error);
        myServicesList.innerHTML = `<div class="error-state">Failed to load services.</div>`;
    }
}

async function deleteService(listingId) {
    if (!confirm('Are you sure you want to delete this service?')) return;
    
    const userId = currentUser.user_id || currentUser.id;
    
    try {
        const response = await fetch(`${API_BASE}delete_service.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                listing_id: listingId,
                provider_id: userId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Service deleted successfully');
            loadMyServices();
        } else {
            alert('Failed to delete service: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting service:', error);
        alert('Failed to delete service');
    }
}

// =====================================================
// VIEW: REQUEST (Post Help Request)
// =====================================================
function renderRequest() {
    const mainContent = document.getElementById('mainContent');
    
    mainContent.innerHTML = `
        <div class="view-header">
            <h2>🙋 Request Help</h2>
            <p>Post what you need help with</p>
        </div>
        
        <div class="card">
            <form id="postRequestForm" onsubmit="submitRequest(event)">
                <div class="form-group">
                    <label>Request Title *</label>
                    <input type="text" id="requestTitle" required placeholder="e.g., Need Python Help for Data Science Project">
                </div>
                
                <div class="form-group">
                    <label>Skill Needed *</label>
                    <select id="requestSkill" required>
                        <option value="">Choose a skill...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea id="requestDescription" required rows="5" 
                        placeholder="Describe what you need help with in detail..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Budget (KES)</label>
                        <input type="number" id="requestBudget" min="0" placeholder="1000">
                    </div>
                    <div class="form-group">
                        <label>Deadline</label>
                        <input type="date" id="requestDeadline">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-primary">Post Request</button>
                    <button type="button" onclick="renderView('discover')" class="btn-ghost">Cancel</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h3>My Requests</h3>
            <div id="myRequestsList">
                <div class="loading">Loading your requests...</div>
            </div>
        </div>
    `;
    
    // Populate skill dropdown
    const skillSelect = document.getElementById('requestSkill');
    const categories = [...new Set(skills.map(s => s.category))];
    categories.forEach(category => {
        const optgroup = document.createElement('optgroup');
        optgroup.label = category;
        skills.filter(s => s.category === category).forEach(skill => {
            const option = document.createElement('option');
            option.value = skill.skill_id;
            option.textContent = skill.skill_name;
            optgroup.appendChild(option);
        });
        skillSelect.appendChild(optgroup);
    });
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('requestDeadline').min = today;
    
    loadMyRequestsList();
}

async function submitRequest(event) {
    event.preventDefault();
    
    const title = document.getElementById('requestTitle').value;
    const skillId = document.getElementById('requestSkill').value;
    const description = document.getElementById('requestDescription').value;
    const budget = document.getElementById('requestBudget').value;
    const deadline = document.getElementById('requestDeadline').value;
    const userId = currentUser.user_id || currentUser.id;
    
    try {
        const response = await fetch(`${API_BASE}create_request.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                seeker_id: userId,
                skill_id: parseInt(skillId),
                title: title,
                description: description,
                budget: budget ? parseFloat(budget) : null,
                deadline: deadline || null
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Request posted successfully! Providers will be able to accept it.');
            document.getElementById('postRequestForm').reset();
            loadMyRequestsList();
        } else {
            alert('Failed to post request: ' + data.message);
        }
    } catch (error) {
        console.error('Error posting request:', error);
        alert('Failed to post request. Please try again.');
    }
}

async function loadMyRequestsList() {
    const myRequestsList = document.getElementById('myRequestsList');
    if (!myRequestsList) return;
    
    const userId = currentUser.user_id || currentUser.id;
    
    try {
        const response = await fetch(`${API_BASE}get_my_requests.php?user_id=${userId}&role=seeker`);
        const data = await response.json();
        
        if (data.success && data.requests.length > 0) {
            myRequestsList.innerHTML = data.requests.map(request => `
                <div class="request-item" style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h4 style="margin: 0;">${escapeHtml(request.title)}</h4>
                            <span class="skill-badge">${escapeHtml(request.skill_name)}</span>
                            <p class="muted" style="margin: 5px 0;">Budget: KES ${request.budget || 'Negotiable'}</p>
                            ${request.provider_first_name ? 
                                `<p class="muted">Provider: ${request.provider_first_name} ${request.provider_last_name}</p>` : 
                                '<p class="muted">No provider yet</p>'
                            }
                        </div>
                        <span class="status-badge ${request.status}">${request.status.replace('_', ' ')}</span>
                    </div>
                </div>
            `).join('');
        } else {
            myRequestsList.innerHTML = `<div class="empty-state">You haven't posted any requests yet.</div>`;
        }
    } catch (error) {
        console.error('Error loading requests:', error);
        myRequestsList.innerHTML = `<div class="error-state">Failed to load requests.</div>`;
    }
}

// =====================================================
// VIEW: MESSAGES
// =====================================================
async function renderMessages() {
    const mainContent = document.getElementById('mainContent');
    
    mainContent.innerHTML = `
        <div class="view-header">
            <h2>💬 Messages</h2>
        </div>
        
        <div class="messages-container" style="display: grid; grid-template-columns: 300px 1fr; gap: 15px; min-height: 500px;">
            <div class="conversations-list card" id="conversationsList" style="overflow-y: auto; max-height: 500px;">
                <div class="loading">Loading conversations...</div>
            </div>
            <div class="chat-window card" id="chatWindow" style="display: flex; flex-direction: column;">
                <div class="empty-chat" style="flex: 1; display: flex; align-items: center; justify-content: center;">
                    <p class="muted">Select a conversation to start chatting</p>
                </div>
            </div>
        </div>
    `;
    
    loadConversations();
    
    // Set up auto-refresh for messages
    messageRefreshInterval = setInterval(() => {
        if (currentChatUser) {
            loadChatMessages(currentChatUser.id, false); // silent refresh
        }
        loadConversations();
    }, 8000);
}

async function loadConversations() {
    const userId = currentUser.user_id || currentUser.id;
    
    try {
        const response = await fetch(`${API_BASE}get_conversations.php?user_id=${userId}`);
        const data = await response.json();
        
        const conversationsList = document.getElementById('conversationsList');
        if (!conversationsList) return;
        
        if (data.success && data.conversations.length > 0) {
            conversationsList.innerHTML = data.conversations.map(conv => `
                <div class="conversation-item ${conv.unread_count > 0 ? 'unread' : ''}" 
                     onclick="openConversation(${conv.partner_id}, '${escapeHtml(conv.first_name)} ${escapeHtml(conv.last_name)}')"
                     style="padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); cursor: pointer; ${currentChatUser && currentChatUser.id == conv.partner_id ? 'background: rgba(255,45,85,0.1);' : ''}">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--pink); display: flex; align-items: center; justify-content: center;">
                            ${conv.first_name[0]}${conv.last_name[0]}
                        </div>
                        <div style="flex: 1; overflow: hidden;">
                            <div style="display: flex; justify-content: space-between;">
                                <strong>${escapeHtml(conv.first_name)} ${escapeHtml(conv.last_name)}</strong>
                                ${conv.unread_count > 0 ? `<span class="badge">${conv.unread_count}</span>` : ''}
                            </div>
                            <p class="muted" style="margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 12px;">
                                ${escapeHtml(conv.last_message || '').substring(0, 30)}...
                            </p>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            conversationsList.innerHTML = `
                <div class="empty-state" style="padding: 20px;">No conversations yet. Start by contacting a provider!</div>
            `;
        }
    } catch (error) {
        console.error('Error loading conversations:', error);
    }
}

async function openConversation(partnerId, partnerName) {
    currentChatUser = { id: partnerId, name: partnerName };
    
    const chatWindow = document.getElementById('chatWindow');
    chatWindow.innerHTML = `
        <div class="chat-header" style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <h3 style="margin: 0;">${escapeHtml(partnerName)}</h3>
        </div>
        <div class="chat-messages" id="chatMessages" style="flex: 1; overflow-y: auto; padding: 15px;">
            <div class="loading">Loading messages...</div>
        </div>
        <div class="chat-input-area" style="padding: 15px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; gap: 10px;">
            <textarea id="messageInput" placeholder="Type your message..." rows="2" style="flex: 1; resize: none;"
                onkeypress="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }"></textarea>
            <button onclick="sendMessage()" class="btn-primary">Send</button>
        </div>
    `;
    
    loadChatMessages(partnerId, true);
    loadConversations(); // Refresh to update active state
}

function openChat(partnerId, partnerName) {
    renderView('messages');
    setTimeout(() => openConversation(partnerId, partnerName), 300);
}

// Contact provider about a specific service - sends initial inquiry message
async function contactProvider(providerId, providerName, serviceTitle, serviceId) {
    const userId = currentUser.user_id || currentUser.id;
    
    // Close the modal first
    closeModal();
    
    // Send an initial inquiry message about the service
    try {
        const inquiryMessage = `Hi! I'm interested in your service: "${serviceTitle}". Can we discuss the details?`;
        
        const response = await fetch(`${API_BASE}send_message.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sender_id: userId,
                receiver_id: providerId,
                message_text: inquiryMessage,
                listing_id: serviceId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Navigate to messages and open conversation
            renderView('messages');
            setTimeout(() => openConversation(providerId, providerName), 300);
        } else {
            // Still open chat even if initial message fails
            renderView('messages');
            setTimeout(() => openConversation(providerId, providerName), 300);
        }
    } catch (error) {
        console.error('Error sending inquiry:', error);
        // Still open chat even if initial message fails
        renderView('messages');
        setTimeout(() => openConversation(providerId, providerName), 300);
    }
}

async function loadChatMessages(partnerId, showLoading = true) {
    const userId = currentUser.user_id || currentUser.id;
    const chatMessages = document.getElementById('chatMessages');
    if (!chatMessages) return;
    
    if (showLoading) {
        chatMessages.innerHTML = '<div class="loading">Loading messages...</div>';
    }
    
    try {
        const response = await fetch(`${API_BASE}get_conversation.php?user1_id=${userId}&user2_id=${partnerId}`);
        const data = await response.json();
        
        if (data.success && data.messages.length > 0) {
            chatMessages.innerHTML = data.messages.map(msg => `
                <div class="message ${msg.sender_id == userId ? 'sent' : 'received'}" 
                     style="max-width: 70%; margin-bottom: 10px; padding: 10px 15px; border-radius: 15px;
                            ${msg.sender_id == userId ? 
                                'margin-left: auto; background: linear-gradient(135deg, var(--pink), var(--pink-soft)); color: white;' : 
                                'background: rgba(255,255,255,0.1);'}">
                    <div>${escapeHtml(msg.message_text)}</div>
                    <div style="font-size: 10px; opacity: 0.7; margin-top: 5px;">${timeAgo(msg.created_at)}</div>
                </div>
            `).join('');
            
            chatMessages.scrollTop = chatMessages.scrollHeight;
        } else {
            chatMessages.innerHTML = `<div class="empty-chat" style="text-align: center; padding: 20px;">No messages yet. Start the conversation!</div>`;
        }
        
        // Update unread counts
        loadUnreadCounts();
        
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

async function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    const messageText = messageInput.value.trim();
    const userId = currentUser.user_id || currentUser.id;
    
    if (!messageText || !currentChatUser) return;
    
    try {
        const response = await fetch(`${API_BASE}send_message.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sender_id: userId,
                receiver_id: currentChatUser.id,
                message_text: messageText
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            messageInput.value = '';
            loadChatMessages(currentChatUser.id, false);
            loadConversations();
        } else {
            alert('Failed to send message');
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('Failed to send message');
    }
}

// =====================================================
// VIEW: PROFILE
// =====================================================
async function renderProfile() {
    const mainContent = document.getElementById('mainContent');
    const userId = currentUser.user_id || currentUser.id;
    
    mainContent.innerHTML = `
        <div class="view-header">
            <h2>👤 My Profile</h2>
        </div>
        <div id="profileContent" style="text-align: center; padding: 40px;">
            <div class="spinner" style="width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.1); border-top-color: var(--pink); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
            <p class="muted" style="margin-top: 15px;">Loading profile...</p>
        </div>
    `;
    
    try {
        const response = await fetch(`${API_BASE}get_profile.php?user_id=${userId}`);
        const text = await response.text();
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Profile API returned invalid JSON:', text);
            throw new Error('Server returned invalid response');
        }
        
        if (data.success && data.user) {
            const user = data.user;
            
            document.getElementById('profileContent').innerHTML = `
                <div class="card">
                    <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 20px;">
                        <div style="width: 100px; height: 100px; border-radius: 50%; background: var(--pink); display: flex; align-items: center; justify-content: center; font-size: 32px; overflow: hidden;">
                            ${user.profile_picture_url ? 
                                `<img src="${user.profile_picture_url}" alt="${user.first_name}" id="profilePicPreview" style="width:100%;height:100%;object-fit:cover;">` : 
                                `${user.first_name[0]}${user.last_name[0]}`
                            }
                        </div>
                        <div>
                            <h2 style="margin: 0;">${escapeHtml(user.first_name)} ${escapeHtml(user.last_name)}</h2>
                            <div class="rating" style="font-size: 18px;">
                                ${'★'.repeat(Math.round(user.average_rating || 0))}${'☆'.repeat(5 - Math.round(user.average_rating || 0))}
                                <span class="muted">${parseFloat(user.average_rating || 0).toFixed(1)} (${user.total_reviews || 0} reviews)</span>
                            </div>
                            <p class="muted">Member since ${new Date(user.date_registered).toLocaleDateString()}</p>
                            <p style="margin-top: 8px;">📧 <strong style="color: var(--pink);">${escapeHtml(user.user_email)}</strong></p>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
                        <div class="stat-card" style="text-align: center; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                            <div style="font-size: 24px; color: var(--pink);">${user.service_count || 0}</div>
                            <div class="muted">Services Posted</div>
                        </div>
                        <div class="stat-card" style="text-align: center; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                            <div style="font-size: 24px; color: var(--pink);">${user.completed_transactions || 0}</div>
                            <div class="muted">Completed Jobs</div>
                        </div>
                    </div>
                </div>
                
                <div class="card" style="background: rgba(255, 45, 85, 0.05); border: 1px solid rgba(255, 45, 85, 0.2);">
                    <h3>📧 Contact Information</h3>
                    <p>Other users can contact you directly at:</p>
                    <p style="font-size: 18px; margin: 10px 0;"><strong style="color: var(--pink);">${escapeHtml(user.user_email)}</strong></p>
                    <p class="muted small">This email is visible to other students for direct communication.</p>
                </div>
                
                <div class="card">
                    <h3>Edit Profile</h3>
                    <form id="editProfileForm" onsubmit="updateProfile(event)">
                        <div class="form-group">
                            <label>Profile Picture</label>
                            <input type="file" id="profilePicture" accept="image/*" onchange="previewProfilePicture(event)">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" id="editFirstName" value="${escapeHtml(user.first_name)}">
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" id="editLastName" value="${escapeHtml(user.last_name)}">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" id="editPhone" value="${user.phone_number || ''}" placeholder="254XXXXXXXXX">
                        </div>
                        
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea id="editBio" rows="4" placeholder="Tell others about yourself...">${user.bio || ''}</textarea>
                        </div>
                        
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </form>
                </div>
                
                <div class="card">
                    <h3>My Reviews</h3>
                    <div id="myReviewsList">
                        <div class="loading">Loading reviews...</div>
                    </div>
                </div>
            `;
            
            // Load reviews
            loadMyReviews(userId);
        } else {
            document.getElementById('profileContent').innerHTML = `
                <div class="error-state" style="padding: 40px; text-align: center;">
                    <div style="font-size: 48px; margin-bottom: 15px;">⚠️</div>
                    <p style="color: #ff6b6b;">${data.message || 'Failed to load profile'}</p>
                    <button onclick="renderProfile()" class="btn-primary" style="margin-top: 15px;">Try Again</button>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        document.getElementById('profileContent').innerHTML = `
            <div class="error-state" style="padding: 40px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 15px;">⚠️</div>
                <p style="color: #ff6b6b;">Failed to load profile. Please try again.</p>
                <p class="muted small">${error.message}</p>
                <button onclick="renderProfile()" class="btn-primary" style="margin-top: 15px;">Retry</button>
            </div>
        `;
    }
}

async function loadMyReviews(userId) {
    try {
        const response = await fetch(`${API_BASE}get_reviews.php?user_id=${userId}`);
        const data = await response.json();
        
        const reviewsList = document.getElementById('myReviewsList');
        if (!reviewsList) return;
        
        if (data.success && data.reviews.length > 0) {
            reviewsList.innerHTML = data.reviews.map(review => `
                <div style="padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong>${escapeHtml(review.reviewer_first_name)} ${escapeHtml(review.reviewer_last_name)}</strong>
                        <span class="rating">${'★'.repeat(review.rating)}${'☆'.repeat(5 - review.rating)}</span>
                    </div>
                    <p style="margin: 10px 0;">${escapeHtml(review.comment || 'No comment')}</p>
                    <p class="muted small">${new Date(review.created_at).toLocaleDateString()}</p>
                </div>
            `).join('');
        } else {
            reviewsList.innerHTML = `<div class="empty-state">No reviews yet.</div>`;
        }
    } catch (error) {
        console.error('Error loading reviews:', error);
    }
}

function previewProfilePicture(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('profilePicPreview');
            if (preview) {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(file);
    }
}

async function updateProfile(event) {
    event.preventDefault();
    
    const userId = currentUser.user_id || currentUser.id;
    const profilePicInput = document.getElementById('profilePicture');
    let pictureUrl = currentUser.profile_picture_url;
    
    // Upload profile picture if selected
    if (profilePicInput.files.length > 0) {
        const formData = new FormData();
        formData.append('profile_picture', profilePicInput.files[0]);
        formData.append('user_id', userId);
        
        try {
            const uploadResponse = await fetch(`${API_BASE}upload_profile_picture.php`, {
                method: 'POST',
                body: formData
            });
            
            const uploadData = await uploadResponse.json();
            
            if (uploadData.success) {
                pictureUrl = uploadData.picture_url;
            } else {
                alert('Failed to upload profile picture: ' + uploadData.message);
                return;
            }
        } catch (error) {
            console.error('Error uploading picture:', error);
            alert('Failed to upload profile picture');
            return;
        }
    }
    
    const firstName = document.getElementById('editFirstName').value;
    const lastName = document.getElementById('editLastName').value;
    const phone = document.getElementById('editPhone').value;
    const bio = document.getElementById('editBio').value;
    
    try {
        const response = await fetch(`${API_BASE}update_profile.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                first_name: firstName,
                last_name: lastName,
                phone_number: phone,
                bio: bio,
                profile_picture_url: pictureUrl
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update currentUser in localStorage
            currentUser.first_name = firstName;
            currentUser.last_name = lastName;
            currentUser.phone_number = phone;
            currentUser.bio = bio;
            currentUser.profile_picture_url = pictureUrl;
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            
            alert('✅ Profile updated successfully!');
            updateUserInfo();
            renderProfile();
        } else {
            alert('Failed to update profile: ' + data.message);
        }
    } catch (error) {
        console.error('Error updating profile:', error);
        alert('Failed to update profile');
    }
}

// =====================================================
// VIEW: SETTINGS
// =====================================================
function renderSettings() {
    const mainContent = document.getElementById('mainContent');
    const email = currentUser.user_email || currentUser.email || '';
    
    mainContent.innerHTML = `
        <div class="view-header">
            <h2>⚙️ Settings</h2>
        </div>
        
        <div class="card">
            <h3>Change Password</h3>
            <form id="changePasswordForm" onsubmit="changePassword(event)">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" id="currentPassword" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" id="newPassword" required>
                    <small class="muted">Must be 6+ characters with uppercase, lowercase, and special character</small>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" id="confirmPassword" required>
                </div>
                <button type="submit" class="btn-primary">Change Password</button>
            </form>
        </div>
        
        <div class="card">
            <h3>Account Information</h3>
            <div style="padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <span class="muted">Email:</span>
                <span style="float: right;">${email}</span>
            </div>
            <div style="padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <span class="muted">Account Type:</span>
                <span style="float: right;">Student</span>
            </div>
            <div style="padding: 10px 0;">
                <span class="muted">Status:</span>
                <span style="float: right; color: #28a745;">Active</span>
            </div>
        </div>
        
        <div class="card" style="border: 1px solid rgba(255,0,0,0.3);">
            <h3 style="color: #ff4444;">Danger Zone</h3>
            <p class="muted">Once you delete your account, there is no going back. Please be certain.</p>
            <button onclick="deleteAccount()" class="btn-ghost" style="color: #ff4444; border-color: #ff4444;">Delete Account</button>
        </div>
    `;
}

async function changePassword(event) {
    event.preventDefault();
    
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const userId = currentUser.user_id || currentUser.id;
    
    // Validate new password
    if (newPassword.length < 6) {
        alert('Password must be at least 6 characters long');
        return;
    }
    
    if (!/[A-Z]/.test(newPassword) || !/[a-z]/.test(newPassword) || !/[^A-Za-z0-9]/.test(newPassword)) {
        alert('Password must contain uppercase, lowercase, and special character');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        alert('Passwords do not match');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}change_password.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                current_password: currentPassword,
                new_password: newPassword
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Password changed successfully!');
            document.getElementById('changePasswordForm').reset();
        } else {
            alert('Failed to change password: ' + data.message);
        }
    } catch (error) {
        console.error('Error changing password:', error);
        alert('Failed to change password');
    }
}

async function deleteAccount() {
    if (!confirm('Are you ABSOLUTELY SURE you want to delete your account? This cannot be undone!')) {
        return;
    }
    
    const password = prompt('Enter your password to confirm account deletion:');
    if (!password) return;
    
    const userId = currentUser.user_id || currentUser.id;
    
    try {
        const response = await fetch(`${API_BASE}delete_account.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: userId,
                password: password
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Account deleted successfully');
            localStorage.removeItem('currentUser');
            window.location.href = 'auth-portal.html';
        } else {
            alert('Failed to delete account: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting account:', error);
        alert('Failed to delete account');
    }
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function timeAgo(datetime) {
    const now = new Date();
    const past = new Date(datetime);
    const seconds = Math.floor((now - past) / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
    return past.toLocaleDateString();
}

function showModal(content) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <button class="modal-close" onclick="closeModal()">×</button>
            ${content}
        </div>
    `;
    document.body.appendChild(modal);
    modal.onclick = (e) => {
        if (e.target === modal) closeModal();
    };
}

function closeModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) modal.remove();
}

// =====================================================
// END OF APP.JS
// =====================================================
