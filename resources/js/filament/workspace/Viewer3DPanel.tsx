import React, { useState } from 'react';
import { useProgress } from '@react-three/drei';
import Dental3DViewer, { ArchMode, CameraPreset } from '../../Components/Dental3D/Dental3DViewer';

/** 3D viewer + loading progress. Lazy-loaded so three.js is only fetched when the 3D view is used. */
export default function Viewer3DPanel(props: {
  teethRecords: Record<string, any>;
  plannedProcedures: Record<string, any[]>;
  selectedTooth: string | null;
  onSelectTooth: (tooth: string) => void;
  onVoiceNote: (tooth: string, proc?: any) => void;
  modelUrl?: string;
}) {
  const { active, progress } = useProgress();
  const [showGums, setShowGums] = useState(true);
  const [isolateTooth, setIsolateTooth] = useState(false);
  const [archMode, setArchMode] = useState<ArchMode>('both');
  const [cameraPreset, setCameraPreset] = useState<CameraPreset>('default');

  return (
    <div className="relative h-full w-full">
      <Dental3DViewer
        teethRecords={props.teethRecords}
        plannedProcedures={props.plannedProcedures}
        selectedTooth={props.selectedTooth}
        onSelectTooth={props.onSelectTooth}
        onSelectSurface={(tooth) => props.onSelectTooth(tooth)}
        showGums={showGums}
        onShowGumsChange={setShowGums}
        isolateTooth={isolateTooth}
        onIsolateToothChange={setIsolateTooth}
        archMode={archMode}
        onArchModeChange={setArchMode}
        cameraPreset={cameraPreset}
        onCameraPresetChange={setCameraPreset}
        onOpenVoiceNote={props.onVoiceNote}
        modelUrl={props.modelUrl}
        cleanMode
      />
      {active && (
        <div className="pointer-events-none absolute inset-x-0 bottom-20 mx-auto w-56 rounded-lg bg-slate-900/90 p-2 text-center text-xs text-slate-200 shadow-lg">
          Loading 3D model… {Math.round(progress)}%
          <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-700">
            <div className="h-full bg-brand-teal transition-all" style={{ width: `${progress}%` }} />
          </div>
        </div>
      )}
    </div>
  );
}
