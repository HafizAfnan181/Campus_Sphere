const API_URL = window.location.origin + '/social-site/backend/api';

// Get token from localStorage
function getToken() {
    return localStorage.getItem('token');
}

// Set token
function setToken(token) {
    localStorage.setItem('token', token);
}

// Remove token (logout)
function removeToken() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'login.html';
}

// API Call helper
async function apiCall(endpoint, method = 'GET', data = null, isFile = false) {
    const options = {
        method: method,
        headers: {}
    };

    if (getToken()) {
        options.headers['Authorization'] = 'Bearer ' + getToken();
    }

    if (!isFile) {
        options.headers['Content-Type'] = 'application/json';
    }

    if (data && !isFile) {
        options.body = JSON.stringify(data);
    } else if (data && isFile) {
        options.body = data;
    }

    try {
        const response = await fetch(`${API_URL}/${endpoint}`, options);
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: 'Network error' };
    }
}

// ========== AUTH FUNCTIONS ==========

// Register
async function register(username, email, password, full_name) {
    const result = await apiCall('auth/register.php', 'POST', {
        username, email, password, full_name
    });
    return result;
}

// Login
async function login(email, password) {
    const result = await apiCall('auth/login.php', 'POST', { email, password });
    if (result.success) {
        setToken(result.token);
        localStorage.setItem('user', JSON.stringify(result.user));
    }
    return result;
}

// Verify Email
async function verifyEmail(email, code) {
    return await apiCall('auth/verify_email.php', 'POST', { email, code });
}

// Forgot Password
async function forgotPassword(email) {
    return await apiCall('auth/forgot_password.php', 'POST', { email });
}

// Reset Password
async function resetPassword(token, new_password) {
    return await apiCall('auth/reset_password.php', 'POST', { token, new_password });
}

// ========== POST FUNCTIONS ==========

// Get Posts
async function getPosts() {
    return await apiCall('posts/get.php');
}

// Create Post
async function createPost(content, imageFile = null) {
    if (imageFile) {
        const formData = new FormData();
        formData.append('content', content);
        formData.append('image', imageFile);
        return await apiCall('posts/create.php', 'POST', formData, true);
    }
    return await apiCall('posts/create.php', 'POST', { content });
}

// Like/Unlike Post
async function toggleLike(post_id) {
    return await apiCall('posts/like.php', 'POST', { post_id });
}

// Comment on Post
async function addComment(post_id, comment) {
    return await apiCall('posts/comment.php', 'POST', { post_id, comment });
}

// Get Comments
async function getComments(post_id) {
    return await apiCall(`posts/comment.php?post_id=${post_id}`);
}

// ========== USER FUNCTIONS ==========

// Get Profile
async function getProfile(user_id = null) {
    const endpoint = user_id ? `users/profile.php?user_id=${user_id}` : 'users/profile.php';
    return await apiCall(endpoint);
}

// Update Profile
async function updateProfile(full_name, bio) {
    return await apiCall('users/profile.php', 'PUT', { full_name, bio });
}

// Search Users
async function searchUsers(query) {
    return await apiCall(`users/search.php?q=${query}`);
}

// ========== FRIEND FUNCTIONS ==========

// Send Friend Request
async function sendFriendRequest(receiver_id) {
    return await apiCall('friends/send_request.php', 'POST', { receiver_id });
}

// Respond to Friend Request
async function respondFriendRequest(request_id, action) {
    return await apiCall('friends/respond.php', 'POST', { request_id, action });
}

// ========== CHAT FUNCTIONS ==========

// Get Chat Users
async function getChatUsers() {
    return await apiCall('chat/users_list.php');
}

// Get Messages
async function getMessages(receiver_id) {
    return await apiCall(`chat/get_messages.php?receiver_id=${receiver_id}`);
}

// Send Message
async function sendMessage(receiver_id, message) {
    return await apiCall('chat/send_message.php', 'POST', { receiver_id, message });
}

// ========== NOTIFICATION FUNCTIONS ==========

// Get Notifications
async function getNotifications() {
    return await apiCall('notifications/get.php');
}

// Check if logged in
if (!getToken() && !window.location.href.includes('login.html') && !window.location.href.includes('signup.html') && !window.location.href.includes('reset-password.html')) {
    window.location.href = 'login.html';
}