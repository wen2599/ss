import { useState, useEffect, useCallback } from 'react';

/**
 * 一个通用的 React Hook 用于处理 API 请求。
 * @param {Function} apiFunc - 要执行的 API 函数 (例如, api.getWinningNumbers)。
 * @param {...any} params - 传递给 API 函数的参数。
 * @returns {{data: any, loading: boolean, error: Error|null, refetch: Function}}
 */
export const useApi = (apiFunc, ...params) => {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // 使用 JSON.stringify 来确保 useEffect/useCallback 能够正确处理对象或数组类型的参数
    const stringifiedParams = JSON.stringify(params);

    const fetchData = useCallback(async () => {
        // 如果没有提供有效的 apiFunc，则不执行任何操作
        if (typeof apiFunc !== 'function') {
            setLoading(false);
            return;
        }

        setLoading(true);
        setError(null);
        try {
            // 解构参数并执行 API 函数
            const result = await apiFunc(...JSON.parse(stringifiedParams));
            setData(result);
        } catch (err) {
            setError(err);
        } finally {
            setLoading(false);
        }
    }, [apiFunc, stringifiedParams]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    return { data, loading, error, refetch: fetchData };
};