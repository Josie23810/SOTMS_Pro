const axios = require('axios');
require('dotenv').config();

class MpesaService {
  constructor() {
    this.baseURL = 'https://sandbox.safaricom.co.ke';
    this.shortcode = process.env.MPESA_SHORTCODE;
    this.passkey = process.env.MPESA_PASSKEY;
  }

  async getAuthToken() {
    const auth = Buffer.from(
      `${process.env.MPESA_CONSUMER_KEY}:${process.env.MPESA_CONSUMER_SECRET}`
    ).toString('base64');
    
    const response = await axios.get(
      `${this.baseURL}/oauth/v1/generate?grant_type=client_credentials`,
      { headers: { Authorization: `Basic ${auth}` } }
    );
    return response.data.access_token;
  }

  async stkPush(phone, amount, reference, description) {
    const token = await this.getAuthToken();
    
    // Safaricom is VERY strict: Format must be YYYYMMDDHHMMSS
    const now = new Date();
    const timestamp = now.getFullYear().toString() +
      (now.getMonth() + 1).toString().padStart(2, '0') +
      now.getDate().toString().padStart(2, '0') +
      now.getHours().toString().padStart(2, '0') +
      now.getMinutes().toString().padStart(2, '0') +
      now.getSeconds().toString().padStart(2, '0');

    const password = Buffer.from(
      `${this.shortcode}${this.passkey}${timestamp}`
    ).toString('base64');

    // Remove any non-digits from phone (e.g., +, spaces, dashes)
    const cleanPhone = phone.toString().replace(/[^\d]/g, '');

    const data = {
      BusinessShortCode: this.shortcode,
      Password: password,
      Timestamp: timestamp,
      TransactionType: 'CustomerPayBillOnline',
      Amount: Math.floor(amount), // Must be a whole number
      PartyA: cleanPhone,
      PartyB: this.shortcode,
      PhoneNumber: cleanPhone,
      CallBackURL: `${process.env.BASE_URL}/api/mpesa/callback`,
      AccountReference: reference.substring(0, 12), // Max 12 chars
      TransactionDesc: description || 'SOTMS Payment'
    };

    console.log("📡 Sending STK Push with Callback:", data.CallBackURL);

    const response = await axios.post(
      `${this.baseURL}/mpesa/stkpush/v1/processrequest`,
      data,
      { headers: { Authorization: `Bearer ${token}` } }
    );
    
    return response.data;
  }
}

module.exports = new MpesaService();