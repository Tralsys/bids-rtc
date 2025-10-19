import {
	Card,
	CardContent,
	FormControl,
	InputLabel,
	MenuItem,
	Select,
	Typography,
} from "@mui/material";
import { memo, use } from "react";
import { LogFile, LogsListResponse } from "../api/adminApi";

interface LogFileListProps {
	logsPromise: Promise<LogsListResponse>;
	onSelectLog: (logName: string) => void;
	selectedLog: string | null;
}

export default memo<LogFileListProps>(function LogFileList({
	logsPromise,
	onSelectLog,
	selectedLog,
}) {
	const logsData = use(logsPromise);

	const formatFileSize = (bytes: number): string => {
		if (bytes < 1024) return `${bytes} B`;
		if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)} KB`;
		return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
	};

	if (!selectedLog && logsData.logs.length > 0) {
		queueMicrotask(() => onSelectLog(logsData.logs[0].name));
	}

	return (
		<Card>
			<CardContent>
				<FormControl fullWidth>
					<InputLabel>ログファイル</InputLabel>
					<Select
						value={selectedLog || ""}
						label="ログファイル"
						onChange={(e) => onSelectLog(e.target.value)}
					>
						{logsData.logs.map((log: LogFile) => (
							<MenuItem key={log.name} value={log.name}>
								{log.name} ({formatFileSize(log.size)}) -{" "}
								{new Date(log.modified).toLocaleString("ja-JP")}
							</MenuItem>
						))}
					</Select>
				</FormControl>
				{logsData.logs.length === 0 && (
					<Typography color="text.secondary" sx={{ mt: 2 }}>
						ログファイルがありません
					</Typography>
				)}
			</CardContent>
		</Card>
	);
});
