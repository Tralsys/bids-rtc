import { Component, ErrorInfo, ReactNode } from "react";
import { Button, Card, CardContent, Typography } from "@mui/material";

interface Props {
	children: ReactNode;
	fallback?: ReactNode;
	onReset?: () => void;
}

interface State {
	hasError: boolean;
	error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
	constructor(props: Props) {
		super(props);
		this.state = { hasError: false, error: null };
	}

	static getDerivedStateFromError(error: Error): State {
		return { hasError: true, error };
	}

	componentDidCatch(error: Error, errorInfo: ErrorInfo) {
		console.error("ErrorBoundary caught an error:", error, errorInfo);
	}

	render() {
		if (this.state.hasError) {
			if (this.props.fallback) {
				return this.props.fallback;
			}

			return (
				<Card sx={{ m: 2 }}>
					<CardContent>
						<Typography variant="h6" color="error" gutterBottom>
							エラーが発生しました
						</Typography>
						<Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
							{this.state.error?.message || "不明なエラー"}
						</Typography>
						<Button
							variant="outlined"
							onClick={() => {
								this.setState({ hasError: false, error: null });
								if (this.props.onReset) {
									this.props.onReset();
								}
							}}
						>
							再試行
						</Button>
					</CardContent>
				</Card>
			);
		}

		return this.props.children;
	}
}
