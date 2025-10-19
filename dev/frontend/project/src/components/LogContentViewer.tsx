import {
	Box,
	Button,
	Card,
	CardContent,
	Stack,
	TextField,
	Typography,
} from "@mui/material";
import { memo, use } from "react";
import { LogContentResponse } from "../api/adminApi";

interface LogContentViewerProps {
	contentPromise: Promise<LogContentResponse>;
	lines: number;
	onLinesChange: (lines: number) => void;
	onRefresh: () => void;
}

export default memo<LogContentViewerProps>(function LogContentViewer({
	contentPromise,
	lines,
	onLinesChange,
	onRefresh,
}) {
	const logData = use(contentPromise);

	return (
		<Card>
			<CardContent>
				<Stack spacing={2}>
					<Stack direction="row" spacing={2} alignItems="center">
						<TextField
							label="表示行数"
							type="number"
							value={lines}
							onChange={(e) => onLinesChange(Number(e.target.value))}
							inputProps={{ min: 1, max: 10000 }}
							sx={{ width: 200 }}
						/>
						<Button variant="contained" onClick={onRefresh}>
							更新
						</Button>
					</Stack>

					<Typography variant="h6">ログ内容</Typography>
					<Box
						component="pre"
						sx={{
							backgroundColor: "#1e1e1e",
							color: "#d4d4d4",
							p: 2,
							borderRadius: 1,
							overflow: "auto",
							maxHeight: "70vh",
							fontSize: "0.875rem",
							fontFamily: "monospace",
							whiteSpace: "pre-wrap",
							wordBreak: "break-all",
						}}
					>
						{logData.content || "ログ内容がありません"}
					</Box>
				</Stack>
			</CardContent>
		</Card>
	);
});
