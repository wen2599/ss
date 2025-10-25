import axios from 'axios';

const recordsService = {
    getMyRecords(token) {
        return axios.get('/api/get-my-records.php', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
    }
};

export default recordsService;
