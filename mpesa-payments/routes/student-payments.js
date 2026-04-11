const express = require('express');
const mpesa = require('../services/mpesa'); // Your working mpesa.js
const pool = require('../config/db'); // Your DB connection

const router = express.Router();

// Student joins session → Pay → Access
router.post('/sessions/:sessionId/join', async (req, res) => {
  try {
    const { sessionId } = req.params;
    const { phone, amount } = req.body;
    const studentId = req.user.id; // From JWT

    // 1. Verify session exists & not expired
    const session = await pool.query(
      'SELECT * FROM sessions WHERE id = $1 AND status = $2',
      [sessionId, 'scheduled']
    );
    
    if (!session.rows[0]) {
      return res.status(404).json({ error: 'Session not found or expired' });
    }

    // 2. Check if already paid
    const existingPayment = await pool.query(
      'SELECT * FROM session_payments WHERE student_id = $1 AND session_id = $2',
      [studentId, sessionId]
    );
    
    if (existingPayment.rows[0]) {
      return res.json({ 
        success: true, 
        status: 'already_paid',
        payment: existingPayment.rows[0]
      });
    }

    // 3. Create payment record
    const reference = `SESSION-${sessionId}-${studentId}-${Date.now()}`;
    const payment = await pool.query(
      `INSERT INTO session_payments (student_id, session_id, tutor_id, amount, phone, reference, status) 
       VALUES ($1, $2, $3, $4, $5, $6, 'PENDING') RETURNING *`,
      [studentId, sessionId, session.rows[0].tutor_id, amount, phone, reference]
    );

    // 4. Send M-Pesa STK Push
    const stkResult = await mpesa.stkPush(phone, amount, reference, `Join Session ${sessionId}`);
    
    // 5. Update checkout ID
    await pool.query(
      'UPDATE session_payments SET checkout_request_id = $1 WHERE id = $2',
      [stkResult.CheckoutRequestID, payment.rows[0].id]
    );

    res.json({
      success: true,
      message: '✅ Check M-Pesa for PIN prompt',
      payment_id: payment.rows[0].id,
      checkout_request_id: stkResult.CheckoutRequestID,
      session: {
        id: sessionId,
        title: session.rows[0].title,
        scheduled_time: session.rows[0].scheduled_time
      }
    });

  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Check payment status
router.get('/payments/:paymentId', async (req, res) => {
  const { paymentId } = req.params;
  const studentId = req.user.id;

  const payment = await pool.query(
    'SELECT sp.*, s.title, s.scheduled_time FROM session_payments sp 
     JOIN sessions s ON sp.session_id = s.id 
     WHERE sp.id = $1 AND sp.student_id = $2',
    [paymentId, studentId]
  );

  res.json(payment.rows[0]);
});

module.exports = router;