import axios from 'axios';

const API_URL = 'https://wenge.cloudns.ch/api/';

const recordsService = {
    getMyRecords(token) {
        return axios.get(API_URL + 'get-my-records.php', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
    }
};

export default recordsService;
