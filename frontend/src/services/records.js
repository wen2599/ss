import api from './api';

const recordsService = {
    getMyRecords(token) {
        return api.get('/get-my-records.php', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
    }
};

export default recordsService;
