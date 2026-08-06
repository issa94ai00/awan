import api from '@/api';
const API_BASE_URL = '/workflows';

export const workflowsService = {
    // Workflows
    getWorkflows(params = {}) {
        return api.get(`${API_BASE_URL}`, { params });
    },

    getWorkflow(id) {
        return api.get(`${API_BASE_URL}/${id}`);
    },

    createWorkflow(data) {
        return api.post(`${API_BASE_URL}`, data);
    },

    updateWorkflow(id, data) {
        return api.put(`${API_BASE_URL}/${id}`, data);
    },

    deleteWorkflow(id) {
        return api.delete(`${API_BASE_URL}/${id}`);
    },

    activateWorkflow(id) {
        return api.post(`${API_BASE_URL}/${id}/activate`);
    },

    deactivateWorkflow(id) {
        return api.post(`${API_BASE_URL}/${id}/deactivate`);
    },

    duplicateWorkflow(id) {
        return api.post(`${API_BASE_URL}/${id}/duplicate`);
    },

    // Workflow Steps
    getSteps(workflowId, params = {}) {
        return api.get(`${API_BASE_URL}/${workflowId}/steps`, { params });
    },

    getStep(workflowId, stepId) {
        return api.get(`${API_BASE_URL}/${workflowId}/steps/${stepId}`);
    },

    createStep(workflowId, data) {
        return api.post(`${API_BASE_URL}/${workflowId}/steps`, data);
    },

    updateStep(workflowId, stepId, data) {
        return api.put(`${API_BASE_URL}/${workflowId}/steps/${stepId}`, data);
    },

    deleteStep(workflowId, stepId) {
        return api.delete(`${API_BASE_URL}/${workflowId}/steps/${stepId}`);
    },

    reorderSteps(workflowId, data) {
        return api.post(`${API_BASE_URL}/${workflowId}/steps/reorder`, data);
    },

    // Workflow Executions
    getExecutions(workflowId, params = {}) {
        return api.get(`${API_BASE_URL}/${workflowId}/executions`, { params });
    },

    getExecution(workflowId, executionId) {
        return api.get(`${API_BASE_URL}/${workflowId}/executions/${executionId}`);
    },

    executeWorkflow(workflowId, data = {}) {
        return api.post(`${API_BASE_URL}/${workflowId}/execute`, data);
    },

    retryExecution(workflowId, executionId) {
        return api.post(`${API_BASE_URL}/${workflowId}/executions/${executionId}/retry`);
    },

    cancelExecution(workflowId, executionId) {
        return api.post(`${API_BASE_URL}/${workflowId}/executions/${executionId}/cancel`);
    },

    getExecutionLogs(workflowId, executionId) {
        return api.get(`${API_BASE_URL}/${workflowId}/executions/${executionId}/logs`);
    },

    // Workflow Triggers
    getTriggers(workflowId) {
        return api.get(`${API_BASE_URL}/${workflowId}/triggers`);
    },

    testTrigger(workflowId, data) {
        return api.post(`${API_BASE_URL}/${workflowId}/triggers/test`, data);
    },

    // Workflow Conditions
    getConditions(workflowId) {
        return api.get(`${API_BASE_URL}/${workflowId}/conditions`);
    },

    // Workflow Statistics
    getWorkflowStats(workflowId, params = {}) {
        return api.get(`${API_BASE_URL}/${workflowId}/stats`, { params });
    },

    getExecutionHistory(workflowId, params = {}) {
        return api.get(`${API_BASE_URL}/${workflowId}/history`, { params });
    }
};

export default workflowsService;
