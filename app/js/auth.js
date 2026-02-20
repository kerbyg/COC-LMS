/**
 * Auth Module - Login, logout, session check
 * Uses the existing PHP AuthAPI.php
 */

import { Api, BASE_URL } from './api.js';

export const Auth = {
    _user: null,

    /**
     * Check if user is logged in (calls AuthAPI check endpoint)
     * Returns user data or null
     */
    async check() {
        try {
            const result = await Api.get('/AuthAPI.php?action=check');
            if (result.success && result.data && result.data.authenticated) {
                this._user = result.data.user;
                return this._user;
            }
            return null;
        } catch (e) {
            return null;
        }
    },

    /**
     * Get current user data (more detailed)
     */
    async getUser() {
        try {
            const result = await Api.get('/AuthAPI.php?action=me');
            if (result.success && result.data) {
                const u = result.data.user;
                // Ensure name field exists (me endpoint only returns first_name/last_name)
                if (!u.name && u.first_name) {
                    u.name = (u.first_name + ' ' + (u.last_name || '')).trim();
                }
                this._user = u;
                return this._user;
            }
            return null;
        } catch (e) {
            return null;
        }
    },

    /**
     * Get cached user (from last check/getUser call)
     */
    user() {
        return this._user;
    },

    /**
     * Logout
     */
    async logout() {
        try {
            await Api.get('/AuthAPI.php?action=logout');
        } catch (e) {
            // Ignore errors
        }
        this._user = null;
        window.location.href = BASE_URL + '/app/login.html';
    },

    /**
     * Require login - redirect to login if not authenticated
     */
    async requireLogin() {
        const user = await this.check();
        if (!user) {
            window.location.href = BASE_URL + '/app/login.html';
            return null;
        }
        return user;
    },

    /**
     * Require specific role
     */
    async requireRole(roles) {
        const user = await this.requireLogin();
        if (!user) return null;

        if (typeof roles === 'string') roles = [roles];
        if (!roles.includes(user.role)) {
            window.location.href = BASE_URL + '/app/index.html';
            return null;
        }
        return user;
    },

    /**
     * Helper: get role display name
     */
    roleName(role) {
        const names = {
            admin: 'Administrator',
            dean: 'Dean',
            instructor: 'Instructor',
            student: 'Student'
        };
        return names[role] || role;
    },

    /**
     * Helper: get user initials
     */
    initials() {
        if (!this._user) return '??';
        const first = (this._user.first_name || this._user.name || '?')[0];
        const last = (this._user.last_name || '?')[0];
        return (first + last).toUpperCase();
    }
};
