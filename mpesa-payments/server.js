require('dotenv').config();
const express = require('express');
const helmet = require('helmet');
const cors = require('cors');
const mpesa = require('./services/mpesa');

const app = express();

// Middleware
app.use(helmet());
app.use(cors());
app.use(express.json());

// 1. Health Check Route
// Useful to verify if ngrok is pointing to the right place
app.get('/', (req, res) => {
  res.json({ 
    status: '🚀 SOTMS M-Pesa Server Active', 
    callback_url: `${process.env.BASE_URL}/api/mpesa/callback` 
  });
});

// 2. STK Push Initiation Route
app.post('/api/mpesa/stkpush', async (req, res) => {
  try {
    const { phone, amount, reference, description } = req.body;
    
    console.log(`\n--- 💳 New Payment Request ---`);
    console.log(`📱 Phone: ${phone}`);
    console.log(`💰 Amount: KSh ${amount}`);
    console.log(`🆔 Ref: ${reference}`);

    // Call the service logic
    const result = await mpesa.stkPush(phone, amount, reference, description);
    
    console.log('✅ STK Push Sent Successfully!');
    console.log(`📝 CheckoutID: ${result.CheckoutRequestID}`);

    res.json({
      success: true,
      message: 'Request sent! Please check your phone for the M-Pesa PIN prompt.',
      checkout_request_id: result.CheckoutRequestID
    });
    
  } catch (error) {
    // Extract the specific error message from Safaricom's response
    const errorMessage = error.response?.data?.errorMessage || error.message;
    console.error('\n❌ SAFARICOM REJECTION:', errorMessage);
    
    res.status(400).json({ 
      success: false,
      error: errorMessage 
    });
  }
});

// 3. M-Pesa Callback Route (CRITICAL for ngrok)
// Safaricom hits this URL after the user enters their PIN
app.post('/api/mpesa/callback', (req, res) => {
  const callbackData = req.body.Body.stkCallback;
  
  console.log('\n🔔 === M-PESA CALLBACK RECEIVED ===');
  console.log('Result Code:', callbackData.ResultCode);
  console.log('Result Desc:', callbackData.ResultDesc);
  
  if (callbackData.ResultCode === 0) {
    console.log('✅ Payment Confirmed for CheckoutID:', callbackData.CheckoutRequestID);
    // Here is where you would normally update your MySQL database
  } else {
    console.log('❌ Payment Failed/Cancelled');
  }

  res.status(200).json({ ResultCode: 0, ResultDesc: 'Accepted' });
});

const PORT = 8000;
app.listen(PORT, () => {
  console.log(`\n🚀 Server running on: http://localhost:${PORT}`);
  console.log(`🔗 Webhook URL: ${process.env.BASE_URL}/api/mpesa/callback`);
  console.log(`🎯 Ensure ngrok is running on port 8000!\n`);
});