import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { getBills } from '../api'; // Re-using getBills to fetch a single bill by filtering
import './BillDetailsPage.css';

const BillDetailsPage = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [bill, setBill] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchBillDetails = async () => {
      try {
        setLoading(true);
        const data = await getBills(); // Fetches all bills for the user
        if (data.success && data.bills) {
          const foundBill = data.bills.find(b => b.id === parseInt(id));
          if (foundBill) {
            setBill(foundBill);
          } else {
            setError('Bill not found.');
          }
        } else {
          setError(data.error || 'Failed to fetch bill details.');
        }
      } catch (err) {
        setError(err.message || 'An error occurred while fetching bill details.');
      } finally {
        setLoading(false);
      }
    };

    fetchBillDetails();
  }, [id]);

  if (loading) return <div>Loading bill details...</div>;
  if (error) return <div className="alert error">Error: {error}</div>;
  if (!bill) return <div className="alert error">Bill not found.</div>;

  return (
    <div className="bill-details-page">
      <h1>Bill Details</h1>
      <div className="bill-info">
        <p><strong>Subject:</strong> {bill.subject}</p>
        <p><strong>Amount:</strong> {bill.amount ? `$${parseFloat(bill.amount).toFixed(2)}` : 'N/A'}</p>
        <p><strong>Due Date:</strong> {bill.due_date || 'N/A'}</p>
        <p><strong>Status:</strong> {bill.status}</p>
        <p><strong>Received At:</strong> {new Date(bill.received_at).toLocaleString()}</p>
        {bill.is_lottery === 1 && (
          <p><strong>Lottery Numbers:</strong> {bill.lottery_numbers || 'N/A'}</p>
        )}
        {/* Display raw email content (for debugging/advanced users) */}
        {/* <div className="raw-email-content">
          <h3>Raw Email Content:</h3>
          <pre>{bill.raw_email}</pre>
        </div> */}
      </div>
      <button onClick={() => navigate('/bills')} className="btn mt-3">Back to Bills</button>
    </div>
  );
};

export default BillDetailsPage;
