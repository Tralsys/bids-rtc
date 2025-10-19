import { auth } from "../firebase/firebase";
import { IS_LOCAL_DEBUG, IS_DOCKER_DEBUG } from "../constants";

const BASE_PATH = IS_LOCAL_DEBUG
	? "http://localhost:8080/signaling"
	: IS_DOCKER_DEBUG
	? `${window.location.origin}/signaling`
	: "/signaling";

export interface LogFile {
	name: string;
	size: number;
	modified: string;
}

export interface LogsListResponse {
	logs: LogFile[];
}

export interface LogContentResponse {
	filename: string;
	content: string;
	lines: number;
}

class AdminApiClient {
	private async getHeaders(): Promise<HeadersInit> {
		const token = await auth.currentUser?.getIdToken();
		return {
			"Content-Type": "application/json",
			...(token ? { Authorization: `Bearer ${token}` } : {}),
		};
	}

	async getLogsList(): Promise<LogsListResponse> {
		const headers = await this.getHeaders();
		const response = await fetch(`${BASE_PATH}/admin/logs`, {
			method: "GET",
			headers,
		});

		if (!response.ok) {
			throw new Error(`Failed to fetch logs list: ${response.statusText}`);
		}

		return response.json();
	}

	async getLogContent(
		filename: string,
		lines: number = 1000
	): Promise<LogContentResponse> {
		const headers = await this.getHeaders();
		const url = new URL(
			`${BASE_PATH}/admin/logs/${encodeURIComponent(filename)}`
		);
		url.searchParams.set("lines", lines.toString());

		const response = await fetch(url.toString(), {
			method: "GET",
			headers,
		});

		if (!response.ok) {
			throw new Error(`Failed to fetch log content: ${response.statusText}`);
		}

		return response.json();
	}
}

export const adminApiClient = new AdminApiClient();
