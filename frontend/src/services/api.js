/**
 * @file Centralized API service for the application.
 * This file exports functions for all interactions with the backend API.
 */

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '';

/**
 * A generic helper function to handle all fetch requests and responses.
 * It centralizes error handling and JSON parsing.
 * @param {string} endpoint The API endpoint to call (e.g., '/login').
 * @param {RequestInit} [options={}] The options for the fetch request.
 * @returns {Promise<any>} The JSON response data from the API.
 * @throws {Error} If the network request fails or the API returns an error.
 */
async function apiService(endpoint, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;
    
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers,
    };

    try {
        const response = await fetch(url, { ...options, headers });

        // Try to parse the JSON response, even for errors, as the backend provides error details.
        const data = await response.json();

        if (!response.ok) {
            // Throw an error with the message from the backend, or a generic one.
            throw new Error(data.error || `Request failed with status ${response.status}`);
        }
        
        // The backend should consistently provide a { success: boolean, ... } structure.
        // This check is a safeguard.
        if (data.success) {
            return data;
        } else {
            throw new Error(data.error || 'An unknown API error occurred.');
        }

    } catch (error) {
        console.error(`API service error calling ${endpoint}:`, error.message);
        // Re-throw the error so UI components can handle it.
        throw error;
    }
}

// --- API Service Functions ---

/** Checks if the user has an active session. */
export const checkSession = () => apiService('/check_session', { credentials: 'include' });

/**
 * Logs a user in.
 * @param {string} email The user's email.
 * @param {string} password The user's password.
 */
export const login = (email, password) => apiService('/login', {
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify({ email, password }),
});

/** Logs the current user out. */
export const logout = () => apiService('/logout', {
    method: 'POST',
    credentials: 'include',
});

/**
 * Registers a new user.
 * @param {string} email The new user's email.
 * @param {string} password The new user's password.
 * @param {string} [username] The new user's optional username.
 */
export const register = (email, password, username) => apiService('/register', {
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify({ email, password, username }),
});

/** Fetches all bills for the authenticated user. */
export const getBills = () => apiService('/get_bills', { credentials: 'include' });

/**
 * Deletes a specific bill.
 * @param {number} billId The ID of the bill to delete.
 */
export const deleteBill = (billId) => apiService('/delete_bill', {
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify({ bill_id: billId }),
});

/**
 * Updates a settlement slip within a bill.
 * @param {number} billId The ID of the bill.
 * @param {number} slipIndex The index of the slip to update.
 * @param {object} settlementResult The new settlement result object.
 */
export const updateSettlement = (billId, slipIndex, settlementResult) => apiService('/update_settlement', {
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify({
        bill_id: billId,
        slip_index: slipIndex,
        settlement_result: settlementResult,
    }),
});

/**
 * Processes a block of text for stats.
 * @param {string} text The text to process.
 */
export const processText = (text) => apiService('/process_text', {
    method: 'POST',
    credentials: 'include',
    body: JSON.stringify({ emailText: text }),
});