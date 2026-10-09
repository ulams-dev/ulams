/** Workflow showcase data (src/data/workflows.json): the true status of every tab lives here. */
import data from "../data/workflows.json";
import { applyWorkflowStatus, type LandingStatus, type WorkflowsData } from "./landing-status.ts";

export const workflowsData = data as WorkflowsData;

export const workflowsModel = (mode: LandingStatus) => applyWorkflowStatus(workflowsData, mode);
