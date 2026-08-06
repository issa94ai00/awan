import axios from 'axios';

const API_BASE_URL = '/api/admin/workflows';

export const workflowsService = {
    // Workflows
    getWorkflows(params = {}) {
        return axios.get(`${API_BASE_URL}`, { params });
    },

    getWorkflow(id) {
        return axios.get(`${API_BASE_URL}/${id}`);
    },

    createWorkflow(data) {
        return axios.post(`${API_BASE_URL}`, data);
    },

    updateWorkflow(id, data) {
        return axios.put(`${API_BASE_URL}/${id}`, data);
    },

    deleteWorkflow(id) {
        return axios.delete(`${API_BASE_URL}/${id}`);
    },

    activateWorkflow(id) {
        return axios.post(`${API_BASE_URL}/${id}/activate`);
    },

    deactivateWorkflow(id) {
        return axios.post(`${API_BASE_URL}/${id}/deactivate`);
    },

    duplicateWorkflow(id) {
        return axios.post(`${API_BASE_URL}/${id}/duplicate`);
    },

    // Workflow Steps
    getSteps(workflowId, params = {}) {
        return axios.get(`${API_BASE_URL}/${workflowId}/steps`, { params });
    },

    getStep(workflowId, stepId) {
        return axios.get(`${API_BASE_URL}/${workflowId}/steps/${stepId}`);
    },

    createStep(workflowId, data) {
        return axios.post(`${API_BASE_URL}/${workflowId}/steps`, data);
    },

    updateStep(workflowId, stepId, data) {
        return axios.put(`${API_BASE_URL}/${workflowId}/steps/${stepId}`, data);
    },

    deleteStep(workflowId, stepId) {
        return axios.delete(`${API_BASE_URL}/${workflowId}/steps/${stepId}`);
    },

    reorderSteps(workflowId, data) {
        return axios.post(`${API_BASE_URL}/${workflowId}/steps/reorder`, data);
    },

    // Workflow Executions
    getExecutions(workflowId, params = {}) {
        return axios.get(`${API_BASE_URL}/${workflowId}/executions`, { params });
    },

    getExecution(workflowId, executionId) {
        return axios.get(`${API_BASE_URL}/${workflowId}/executions/${executionId}`);
    },

    executeWorkflow(workflowId, data = {}) {
        return axios.post(`${API_BASE_URL}/${workflowId}/execute`, data);
    },

    retryExecution(workflowId, executionId) {
        return axios.post(`${API_BASE_URL}/${workflowId}/executions/${executionId}/retry`);
    },

    cancelExecution(workflowId, executionId) {
        return axios.post(`${API_BASE_URL}/${workflowId}/executions/${executionId}/cancel`);
    },

    getExecutionLogs(workflowId, executionId) {
        return axios.get(`${API_BASE_URL}/${workflowId}/executions/${executionId}/logs`);
    },

    // Workflow Triggers
    getTriggers(workflowId) {
        return axios.get(`${API_BASE_URL}/${workflowId}/triggers`);
    },

    testTrigger(workflowId, data) {
        return axios.post(`${API_BASE_URL}/${workflowId}/triggers/test`, data);
    },

    // Workflow Conditions
    getConditions(workflowId) {
        return axios.get(`${API_BASE_URL}/${workflowId}/conditions`);
    },

    // Workflow Statistics
    getWorkflowStats(workflowId, params = {}) {
        return axios.get(`${API_BASE_URL}/${workflowId}/stats`, { params });
    },

    getExecutionHistory(workflowId, params = {}) {
        return axios.get(`${API_BASE_URL}/${workflowId}/history`, { params });
    }
};

export default workflowsService;
