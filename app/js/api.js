/**
 * API Wrapper - All fetch calls go through here
 * Handles auth, errors, and base URL
 */

const BASE_URL = '/COC-LMS';
const API_URL = BASE_URL + '/api';

export const Api = {

    /**
     * Get JWT token from localStorage
     */
    _getToken() {
        return localStorage.getItem('jwt_token') || null;
    },

    /**
     * Build auth headers — always include JWT if available
     */
    _authHeaders(extra = {}) {
        const headers = { 'Accept': 'application/json', ...extra };
        const token = this._getToken();
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    },

    /**
     * GET request
     */
    async get(endpoint) {
        const response = await fetch(API_URL + endpoint, {
            method: 'GET',
            credentials: 'include',
            headers: this._authHeaders()
        });
        return this._handleResponse(response);
    },

    /**
     * POST request with JSON body
     */
    async post(endpoint, data = {}) {
        const response = await fetch(API_URL + endpoint, {
            method: 'POST',
            credentials: 'include',
            headers: this._authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(data)
        });
        return this._handleResponse(response);
    },

    /**
     * POST request with FormData (for file uploads)
     */
    async postForm(endpoint, formData) {
        const response = await fetch(API_URL + endpoint, {
            method: 'POST',
            credentials: 'include',
            headers: this._authHeaders(),
            body: formData
        });
        return this._handleResponse(response);
    },

    /**
     * Handle response - parse JSON and check for auth errors
     */
    async _handleResponse(response) {
        const data = await response.json();

        // If unauthorized, clear token and redirect to login
        if (response.status === 401) {
            localStorage.removeItem('jwt_token');
            window.location.href = BASE_URL + '/app/login.html';
            return data;
        }

        return data;
    }
};

// Export BASE_URL for use in other modules
export { BASE_URL };
