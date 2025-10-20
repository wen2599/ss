import React, { useEffect, useState } from 'react';
import { getBills, deleteBill } from '../api';
import { Link } from 'react-router-dom';
import './BillsPage.css';

const BillsPage = () => {
  const [bills, setBills] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [message, setMessage] = useState(null);

  const fetchBills = async () => {
    try {
      setLoading(true);
      const data = await getBills();
      if (data.success) {
        setBills(data.bills);
      } else {
        setError(data.error || 'Failed to fetch bills.');
      }
    } catch (err) {
      setError(err.message || 'An error occurred while fetching bills.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBills();
  }, []);

  const handleDelete = async (billId) => {
    if (window.confirm('Are you sure you want to delete this bill?')) {
      try {
        const data = await deleteBill(billId);
        if (data.message) {
          setMessage(data.message);
          fetchBills(); // Refresh the list after deletion
        }
      } catch (err) {
        setError(err.message || 'Failed to delete bill.');
      }
    }
  };

  if (loading) return <div>Loading bills...</div>;
  if (error) return <div className="alert error">Error: {error}</div>;

  return (
    <div className="bills-page">
      <h1>My Bills</h1>
      {message && <div className="alert success">{message}</div>}
      {bills.length === 0 ? (
        <p>No bills found. Start forwarding your bills to your registered email!</p>
      ) : (
        <table className="bills-table">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Amount</th>
              <th>Due Date</th>
              <th>Status</th>
              <th>Received At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {bills.map((bill) => (
              <tr key={bill.id}>
                <td><Link to={`/bills/${bill.id}`}>{bill.subject}</Link></td>
                <td>{bill.amount ? `$${parseFloat(bill.amount).toFixed(2)}` : 'N/A'}</td>
                <td>{bill.due_date || 'N/A'}</td>
                <td>{bill.status}</td>
                <td>{new Date(bill.received_at).toLocaleDateString()}</td>
                <td className="actions">
                  <button onClick={() => handleDelete(bill.id)} className="btn btn-danger">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default BillsPage;
