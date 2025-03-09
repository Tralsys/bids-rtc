import { useState, useMemo, useEffect, useCallback } from "react";
import { FieldInfo } from "../webrtc/BIDSSharedMemoryDataFieldInfo";
import { useBidsDataProviderContextValue } from "./BidsDataProvider";
import { BIDSSharedMemoryData } from "../webrtc/BIDSSharedMemoryData";

export function useBsmdValue<TValueType>(
	fieldInfo: FieldInfo<`BSMD.${string}`, TValueType>
): TValueType | undefined {
	const context = useBidsDataProviderContextValue();
	const [value, setValue] = useState<TValueType | undefined>(undefined);
	const fieldPath = useMemo(() => fieldInfo._path.split("."), [fieldInfo]);
	const onBsmdChanged = useCallback(
		(bsmd: BIDSSharedMemoryData) => {
			// eslint-disable-next-line @typescript-eslint/no-explicit-any
			let v: any = undefined;
			for (const path of fieldPath) {
				if (path === "BSMD") {
					v = bsmd;
				} else {
					// eslint-disable-next-line @typescript-eslint/no-explicit-any
					v = (v as any)[path];
				}
			}
			setValue((prev) => {
				if (prev != null && fieldInfo._equal(prev, v)) {
					return prev;
				}
				return v;
			});
		},
		[fieldInfo, fieldPath]
	);

	useEffect(() => {
		if (context == null) {
			return;
		}
		context._bsmdListenerList.push(onBsmdChanged);
		return () => {
			const idx = context._bsmdListenerList.indexOf(onBsmdChanged);
			if (0 <= idx) {
				context._bsmdListenerList.splice(idx, 1);
			}
		};
	}, [context, onBsmdChanged]);

	return value;
}

export function useBIDSPanelValue(index: number): number | undefined {
	const context = useBidsDataProviderContextValue();
	const [value, setValue] = useState<number | undefined>(undefined);
	const onPanelChanged = useCallback(
		(panel: number[]) => {
			setValue(panel[index]);
		},
		[index]
	);

	useEffect(() => {
		if (context == null) {
			return;
		}
		context._panelListenerList.push(onPanelChanged);
		return () => {
			const idx = context._panelListenerList.indexOf(onPanelChanged);
			if (0 <= idx) {
				context._panelListenerList.splice(idx, 1);
			}
		};
	}, [context, onPanelChanged]);

	return value;
}

export function useBIDSSoundValue(index: number): number | undefined {
	const context = useBidsDataProviderContextValue();
	const [value, setValue] = useState<number | undefined>(undefined);
	const onSoundChanged = useCallback(
		(sound: number[]) => {
			setValue(sound[index]);
		},
		[index]
	);

	useEffect(() => {
		if (context == null) {
			return;
		}
		context._soundListenerList.push(onSoundChanged);
		return () => {
			const idx = context._soundListenerList.indexOf(onSoundChanged);
			if (0 <= idx) {
				context._soundListenerList.splice(idx, 1);
			}
		};
	}, [context, onSoundChanged]);

	return value;
}
