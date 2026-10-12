import React, { Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { OrbitControls, Center, useGLTF, Html } from '@react-three/drei';
import * as THREE from 'three';
import InteractiveTooth from './InteractiveTooth';
import { CONDITION_COLORS, CONDITION_LABELS, ToothRecord } from './types';
import { Eye, EyeOff, Layers, Camera, Crosshair, Mic } from 'lucide-react';

export type ArchMode = 'both' | 'upper' | 'lower';
export type CameraPreset = 'default' | 'frontal' | 'upper_occlusal' | 'lower_occlusal' | 'right_bite' | 'left_bite';

export interface Dental3DViewerProps {
  teethRecords: Record<string, ToothRecord>;
  plannedProcedures?: Record<string, any[]>;
  selectedTooth: string | null;
  onSelectTooth: (toothNumber: string) => void;
  onDoubleClickTooth?: (toothNumber: string) => void;
  modelUrl?: string;
  cleanMode?: boolean;

  // Clinical Control Extensions
  showGums?: boolean;
  onShowGumsChange?: (show: boolean) => void;
  isolateTooth?: boolean;
  onIsolateToothChange?: (isolate: boolean) => void;
  archMode?: ArchMode;
  onArchModeChange?: (mode: ArchMode) => void;
  cameraPreset?: CameraPreset;
  onCameraPresetChange?: (preset: CameraPreset) => void;
  onOpenVoiceNote?: (toothNumber: string, procedure?: any) => void;
}

// Extract FDI tooth number (11-48) from node name
export function extractToothNumber(nodeName: string): string | null {
  const match = nodeName.match(/\b([1-4][1-8])\b/) || nodeName.match(/(\d{2})/);
  if (match) {
    const num = parseInt(match[1], 10);
    if (
      (num >= 11 && num <= 18) ||
      (num >= 21 && num <= 28) ||
      (num >= 31 && num <= 38) ||
      (num >= 41 && num <= 48)
    ) {
      return match[1];
    }
  }
  return null;
}

// FDI Quadrant helper functions
export function isUpperTooth(toothNumber: string): boolean {
  const n = parseInt(toothNumber, 10);
  return (n >= 11 && n <= 18) || (n >= 21 && n <= 28);
}

export function isLowerTooth(toothNumber: string): boolean {
  const n = parseInt(toothNumber, 10);
  return (n >= 31 && n <= 38) || (n >= 41 && n <= 48);
}

// Check if node is soft tissue (gums, jaw, tongue, etc.)
export function isSoftTissueNode(nodeName: string): boolean {
  const lower = nodeName.toLowerCase();
  return (
    /gum|gingiva|jaw|tongue|mouth|base|soft|wet/.test(lower) ||
    /\bobject_4\b/.test(lower) ||
    /\bobject_8\b/.test(lower)
  );
}

// Check if node is a structural/bounding box artifact
export function isIgnoredNode(nodeName: string): boolean {
  const lower = nodeName.toLowerCase();
  return lower.includes('cube') || lower.includes('bounds');
}

const CAMERA_PRESET_CONFIGS: Record<CameraPreset, { pos: [number, number, number]; target: [number, number, number] }> = {
  default: { pos: [0, 1.5, 7.5], target: [0, 0, 0] },
  frontal: { pos: [0, 0, 7.5], target: [0, 0, 0] },
  upper_occlusal: { pos: [0, -6.5, 1.2], target: [0, 0.8, 0] },
  lower_occlusal: { pos: [0, 6.5, 1.2], target: [0, -0.8, 0] },
  right_bite: { pos: [7.5, 0, 0], target: [0, 0, 0] },
  left_bite: { pos: [-7.5, 0, 0], target: [0, 0, 0] },
};

// Smooth Camera Controller Component
const CameraController: React.FC<{
  preset: CameraPreset;
  controlsRef: React.RefObject<any>;
  isolatedToothPos: THREE.Vector3 | null;
}> = ({ preset, controlsRef, isolatedToothPos }) => {
  const { camera } = useThree();
  const targetCamPos = useRef(new THREE.Vector3(...CAMERA_PRESET_CONFIGS.default.pos));
  const targetLookAt = useRef(new THREE.Vector3(...CAMERA_PRESET_CONFIGS.default.target));
  const isTransitioning = useRef(false);

  useEffect(() => {
    if (isolatedToothPos) {
      targetLookAt.current.copy(isolatedToothPos);
      targetCamPos.current.set(
        isolatedToothPos.x * 1.3,
        isolatedToothPos.y + 0.3,
        isolatedToothPos.z + 4.2
      );
      isTransitioning.current = true;
    } else {
      const config = CAMERA_PRESET_CONFIGS[preset] || CAMERA_PRESET_CONFIGS.default;
      targetCamPos.current.set(...config.pos);
      targetLookAt.current.set(...config.target);
      isTransitioning.current = true;
    }
  }, [preset, isolatedToothPos]);

  useFrame((_, delta) => {
    if (!isTransitioning.current) return;

    const speed = Math.min(1, delta * 5.5);
    camera.position.lerp(targetCamPos.current, speed);

    if (controlsRef.current) {
      controlsRef.current.target.lerp(targetLookAt.current, speed);
      controlsRef.current.update();
    }

    if (
      camera.position.distanceTo(targetCamPos.current) < 0.05 &&
      (!controlsRef.current || controlsRef.current.target.distanceTo(targetLookAt.current) < 0.05)
    ) {
      camera.position.copy(targetCamPos.current);
      if (controlsRef.current) {
        controlsRef.current.target.copy(targetLookAt.current);
        controlsRef.current.update();
      }
      isTransitioning.current = false;
    }
  });

  return null;
};

// GLTF Dental Arch Model Component
const GLTFDentalArch: React.FC<{
  modelUrl: string;
  teethRecords: Record<string, ToothRecord>;
  plannedProcedures?: Record<string, any[]>;
  selectedTooth: string | null;
  onSelectTooth: (toothNumber: string) => void;
  onDoubleClickTooth?: (toothNumber: string) => void;
  showGums: boolean;
  isolateTooth: boolean;
  archMode: ArchMode;
  onOpenVoiceNote?: (toothNumber: string, procedure?: any) => void;
  onToothCentersCalculated?: (map: Map<string, THREE.Vector3>) => void;
}> = ({
  modelUrl,
  teethRecords,
  plannedProcedures,
  selectedTooth,
  onSelectTooth,
  onDoubleClickTooth,
  showGums,
  isolateTooth,
  archMode,
  onOpenVoiceNote,
  onToothCentersCalculated,
}) => {
  const gltf = useGLTF(modelUrl, true) as any;
  const [hoveredTooth, setHoveredTooth] = useState<string | null>(null);
  const [hoveredPos, setHoveredPos] = useState<THREE.Vector3 | null>(null);

  // Clone scene to preserve transforms and build tooth metadata
  const { scene, nodeMap, softTissueMap, toothCenters } = useMemo(() => {
    if (!gltf || !gltf.scene) {
      return { scene: new THREE.Group(), nodeMap: new Map(), softTissueMap: new Map(), toothCenters: new Map() };
    }

    const cloned = gltf.scene.clone(true);
    const map = new Map<string, string>();
    const tissueMap = new Map<string, boolean>(); // uuid -> isUpper
    const centers = new Map<string, THREE.Vector3>();
    const nodesToRemove: THREE.Object3D[] = [];

    let autoIndex = 1;
    const fdiList = [
      '18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28',
      '48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38',
    ];

    cloned.traverse((child: any) => {
      if (child.isMesh) {
        if (isIgnoredNode(child.name)) {
          nodesToRemove.push(child);
          return;
        }

        if (child.material) {
          child.material = child.material.clone();
        } else {
          child.material = new THREE.MeshStandardMaterial();
        }

        if (isSoftTissueNode(child.name)) {
          child.raycast = () => null;
        } else {
          let num = extractToothNumber(child.name);
          if (!num) {
            num = fdiList[autoIndex - 1] || String(10 + autoIndex);
            autoIndex++;
          }
          map.set(child.uuid, num);
          child.castShadow = true;
          child.receiveShadow = true;
        }
      }
    });

    nodesToRemove.forEach((node) => node.removeFromParent());

    const box = new THREE.Box3().setFromObject(cloned);
    const size = new THREE.Vector3();
    const center = new THREE.Vector3();
    box.getSize(size);
    box.getCenter(center);
    const maxDim = Math.max(size.x, size.y, size.z);

    if (maxDim > 0) {
      const scaleFactor = 6.5 / maxDim;
      cloned.scale.set(scaleFactor, scaleFactor, scaleFactor);
      cloned.position.set(-center.x * scaleFactor, -center.y * scaleFactor, -center.z * scaleFactor);
    }

    // Now calculate scaled bounding centers for teeth and soft tissue
    cloned.traverse((child: any) => {
      if (child.isMesh) {
        const childBox = new THREE.Box3().setFromObject(child);
        const childCenter = new THREE.Vector3();
        childBox.getCenter(childCenter);

        if (isSoftTissueNode(child.name)) {
          tissueMap.set(child.uuid, childCenter.y >= 0);
        } else {
          const num = map.get(child.uuid);
          if (num && !centers.has(num)) {
            centers.set(num, childCenter);
          }
        }
      }
    });

    return { scene: cloned, nodeMap: map, softTissueMap: tissueMap, toothCenters: centers };
  }, [gltf]);

  useEffect(() => {
    if (onToothCentersCalculated) {
      onToothCentersCalculated(toothCenters);
    }
  }, [toothCenters, onToothCentersCalculated]);

  // Declarative Mesh Visibility Engine (Show Gums, Arch Mode, Solo Tooth Isolation)
  useEffect(() => {
    scene.traverse((child: any) => {
      if (child.isMesh) {
        if (isSoftTissueNode(child.name)) {
          const isUpper = softTissueMap.get(child.uuid) ?? true;
          if (!showGums || isolateTooth) {
            child.visible = false;
          } else if (archMode === 'upper') {
            child.visible = isUpper;
          } else if (archMode === 'lower') {
            child.visible = !isUpper;
          } else {
            child.visible = true;
          }
        } else {
          const toothNum = nodeMap.get(child.uuid);
          if (toothNum) {
            if (isolateTooth) {
              child.visible = toothNum === selectedTooth;
            } else if (archMode === 'upper') {
              child.visible = isUpperTooth(toothNum);
            } else if (archMode === 'lower') {
              child.visible = isLowerTooth(toothNum);
            } else {
              child.visible = true;
            }
          }
        }
      }
    });
  }, [scene, nodeMap, softTissueMap, showGums, isolateTooth, archMode, selectedTooth]);

  // Apply condition colors and highlights
  useEffect(() => {
    scene.traverse((child: any) => {
      if (child.isMesh) {
        if (isSoftTissueNode(child.name)) {
          child.material.color.set('#E18A8A');
          child.material.roughness = 0.5;
        } else {
          const toothNum = nodeMap.get(child.uuid);
          if (toothNum) {
            const record = teethRecords[toothNum];
            const procs = plannedProcedures?.[toothNum] || [];
            const hasPlanned = procs.some((p: any) => p.status === 'planned' || p.status === 'in_progress');
            const hasCompleted = procs.some((p: any) => p.status === 'completed');
            const condition = record?.condition || (hasCompleted ? 'composite_filled' : 'healthy');
            const isSelected = selectedTooth === toothNum;
            const isHovered = hoveredTooth === toothNum;
            const conditionColor = CONDITION_COLORS[condition] || CONDITION_COLORS.healthy;

            let displayColor = conditionColor;
            if (isSelected) {
              displayColor = '#10B981'; // Emerald
            } else if (isHovered) {
              displayColor = '#60A5FA'; // Sky Blue
            }

            child.material.color.set(displayColor);
            child.material.map = null;

            if (hasPlanned && !isSelected && !isHovered) {
              child.material.emissive.set('#8B5CF6');
              child.material.emissiveIntensity = 0.4;
            } else {
              child.material.emissive.set('#000000');
              child.material.emissiveIntensity = 0;
            }

            const isMissing = condition === 'missing';
            const isImplant = condition === 'implant';

            child.material.roughness = isImplant ? 0.2 : 0.3;
            child.material.metalness = isImplant ? 0.85 : 0.05;
            child.material.transparent = isMissing;
            child.material.opacity = isMissing ? 0.2 : 1.0;
            child.material.wireframe = isMissing;
            child.material.needsUpdate = true;
          }
        }
      }
    });
  }, [scene, nodeMap, teethRecords, plannedProcedures, selectedTooth, hoveredTooth]);

  const activeHoverProcedures = hoveredTooth ? plannedProcedures?.[hoveredTooth] || [] : [];

  return (
    <group>
      <primitive
        object={scene}
        onPointerDown={(e: any) => {
          e.stopPropagation();
          const toothNum = nodeMap.get(e.object.uuid);
          if (toothNum) {
            onSelectTooth(toothNum);
          }
        }}
        onPointerOver={(e: any) => {
          e.stopPropagation();
          const toothNum = nodeMap.get(e.object.uuid);
          if (toothNum) {
            setHoveredTooth(toothNum);
            setHoveredPos(e.point);
          }
        }}
        onPointerOut={(e: any) => {
          e.stopPropagation();
          setHoveredTooth(null);
          setHoveredPos(null);
        }}
        onDoubleClick={(e: any) => {
          e.stopPropagation();
          const toothNum = nodeMap.get(e.object.uuid);
          if (toothNum && onDoubleClickTooth) {
            onDoubleClickTooth(toothNum);
          }
        }}
      />

      {/* Clinical Tooltip with Full Procedure & Anesthetic Badges */}
      {hoveredTooth && hoveredPos && (
        <Html position={hoveredPos} center style={{ pointerEvents: 'auto', zIndex: 30 }}>
          <div className="bg-slate-900/95 text-slate-100 p-3 rounded-xl shadow-2xl min-w-[220px] max-w-[280px] border border-slate-700/80 backdrop-blur-md flex flex-col gap-2 text-xs">
            <div className="flex items-center justify-between border-b border-slate-800 pb-1.5">
              <span className="font-bold text-sm text-white">Tooth #{hoveredTooth}</span>
              <span
                className="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                style={{
                  backgroundColor: `${CONDITION_COLORS[teethRecords[hoveredTooth]?.condition || 'healthy']}25`,
                  color: CONDITION_COLORS[teethRecords[hoveredTooth]?.condition || 'healthy'],
                }}
              >
                {CONDITION_LABELS[teethRecords[hoveredTooth]?.condition || 'healthy']?.label}
              </span>
            </div>

            {/* Notes if any */}
            {teethRecords[hoveredTooth]?.notes && (
              <div className="text-[11px] text-slate-300 italic">
                "{teethRecords[hoveredTooth].notes}"
              </div>
            )}

            {/* Procedure Medical Details */}
            {activeHoverProcedures.length > 0 ? (
              <div className="flex flex-col gap-1.5">
                <span className="text-[10px] font-semibold uppercase text-slate-400 tracking-wider">
                  Procedures ({activeHoverProcedures.length}):
                </span>
                {activeHoverProcedures.map((proc: any, idx: number) => {
                  const isCompleted = proc.status === 'completed';
                  return (
                    <div
                      key={idx}
                      className="p-2 rounded-lg bg-slate-950/70 border border-slate-800 flex flex-col gap-1"
                    >
                      <div className="flex items-center justify-between text-[11px] font-semibold">
                        <span className="text-slate-200">{proc.procedure_code?.title || 'Procedure'}</span>
                        <span
                          className={`text-[9px] px-1.5 py-0.5 rounded font-bold uppercase ${
                            isCompleted ? 'bg-emerald-500/20 text-emerald-400' : 'bg-purple-500/20 text-purple-400'
                          }`}
                        >
                          {proc.status}
                        </span>
                      </div>

                      {/* Anesthetic details badge */}
                      {proc.anesthetic_agent && (
                        <div className="text-[10px] text-emerald-300 flex items-center gap-1 font-medium">
                          <span>💉 {proc.anesthetic_agent}</span>
                          {proc.anesthetic_dosage && <span>({proc.anesthetic_dosage})</span>}
                          {proc.anesthetic_technique && <span className="text-slate-400">· {proc.anesthetic_technique}</span>}
                        </div>
                      )}

                      {/* Materials used */}
                      {proc.materials_used_json && (
                        <div className="text-[10px] text-slate-300">
                          <span className="text-slate-400 font-medium">Materials: </span>
                          {Array.isArray(proc.materials_used_json)
                            ? proc.materials_used_json.join(', ')
                            : String(proc.materials_used_json)}
                        </div>
                      )}

                      {/* Tools used */}
                      {proc.tools_used_json && (
                        <div className="text-[10px] text-slate-300">
                          <span className="text-slate-400 font-medium">Tools: </span>
                          {Array.isArray(proc.tools_used_json)
                            ? proc.tools_used_json.join(', ')
                            : String(proc.tools_used_json)}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            ) : (
              <div className="text-[11px] text-slate-400">No procedures recorded yet.</div>
            )}

            {/* Quick Action Buttons inside Tooltip */}
            <div className="pt-1.5 border-t border-slate-800 flex flex-col gap-1.5">
              {onOpenVoiceNote && (
                <button
                  type="button"
                  onClick={(e) => {
                    e.stopPropagation();
                    onOpenVoiceNote(hoveredTooth, activeHoverProcedures[0]);
                  }}
                  className="w-full flex items-center justify-center gap-1.5 py-1.5 px-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg font-bold text-[10px] shadow transition-colors"
                >
                  <Mic className="w-3 h-3" />
                  Send WhatsApp Post-Op Voice Note
                </button>
              )}
              <div className="text-[9px] text-slate-500 uppercase text-center font-semibold">
                Double-click tooth for full details
              </div>
            </div>
          </div>
        </Html>
      )}
    </group>
  );
};

// Procedural Dental Arch Fallback
const ProceduralDentalArch: React.FC<{
  teethRecords: Record<string, ToothRecord>;
  selectedTooth: string | null;
  onSelectTooth: (toothNumber: string) => void;
  showGums?: boolean;
  isolateTooth?: boolean;
  archMode?: ArchMode;
}> = ({
  teethRecords,
  selectedTooth,
  onSelectTooth,
  showGums = true,
  isolateTooth = false,
  archMode = 'both',
}) => {
  const toothNumbers = useMemo(() => {
    const list: Array<{ number: string; pos: [number, number, number]; rot: [number, number, number] }> = [];

    const upperTeeth = ['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'];
    const totalUpper = upperTeeth.length;
    upperTeeth.forEach((num, idx) => {
      const angle = Math.PI * 0.15 + (idx / (totalUpper - 1)) * Math.PI * 0.7;
      const radiusX = 2.4;
      const radiusZ = 2.8;
      const x = Math.cos(angle) * radiusX;
      const z = Math.sin(angle) * radiusZ - 1.2;
      const y = 0.6;
      list.push({ number: num, pos: [x, y, z], rot: [0, -angle + Math.PI / 2, 0] });
    });

    const lowerTeeth = ['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'];
    const totalLower = lowerTeeth.length;
    lowerTeeth.forEach((num, idx) => {
      const angle = Math.PI * 0.15 + (idx / (totalLower - 1)) * Math.PI * 0.7;
      const radiusX = 2.25;
      const radiusZ = 2.6;
      const x = Math.cos(angle) * radiusX;
      const z = Math.sin(angle) * radiusZ - 1.2;
      const y = -0.6;
      list.push({ number: num, pos: [x, y, z], rot: [Math.PI, -angle + Math.PI / 2, 0] });
    });

    return list;
  }, []);

  const toothGeometry = useMemo(() => new THREE.CapsuleGeometry(0.22, 0.45, 8, 16), []);

  const visibleTeeth = toothNumbers.filter((t) => {
    if (isolateTooth) return t.number === selectedTooth;
    if (archMode === 'upper') return isUpperTooth(t.number);
    if (archMode === 'lower') return isLowerTooth(t.number);
    return true;
  });

  return (
    <group>
      {showGums && !isolateTooth && (archMode === 'both' || archMode === 'upper') && (
        <mesh position={[0, 0.75, -0.2]} rotation={[Math.PI / 2, 0, 0]} raycast={() => null}>
          <torusGeometry args={[2.3, 0.35, 16, 32, Math.PI * 0.8]} />
          <meshStandardMaterial color="#E18A8A" roughness={0.6} />
        </mesh>
      )}

      {showGums && !isolateTooth && (archMode === 'both' || archMode === 'lower') && (
        <mesh position={[0, -0.75, -0.2]} rotation={[Math.PI / 2, 0, 0]} raycast={() => null}>
          <torusGeometry args={[2.15, 0.35, 16, 32, Math.PI * 0.8]} />
          <meshStandardMaterial color="#E18A8A" roughness={0.6} />
        </mesh>
      )}

      {visibleTeeth.map((t) => {
        const condition = teethRecords[t.number]?.condition || 'healthy';
        const isSelected = selectedTooth === t.number;
        return (
          <InteractiveTooth
            key={t.number}
            toothNumber={t.number}
            geometry={toothGeometry}
            condition={condition}
            isSelected={isSelected}
            onSelect={onSelectTooth}
            position={t.pos}
            rotation={t.rot}
          />
        );
      })}
    </group>
  );
};

// Error Boundary Fallback Wrapper
class GLTFBoundary extends React.Component<
  { children: React.ReactNode; fallback: React.ReactNode },
  { hasError: boolean }
> {
  constructor(props: { children: React.ReactNode; fallback: React.ReactNode }) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error: any) {
    console.warn('GLTF load failed, switching to procedural 3D arch fallback:', error);
  }

  render() {
    if (this.state.hasError) {
      return this.props.fallback;
    }
    return this.props.children;
  }
}

export const Dental3DViewer: React.FC<Dental3DViewerProps> = ({
  teethRecords = {},
  plannedProcedures = {},
  selectedTooth,
  onSelectTooth,
  onDoubleClickTooth,
  modelUrl = '/models/teeth-seperated-optimized.glb',
  cleanMode = false,
  showGums: externalShowGums,
  onShowGumsChange,
  isolateTooth: externalIsolateTooth,
  onIsolateToothChange,
  archMode: externalArchMode,
  onArchModeChange,
  cameraPreset: externalCameraPreset,
  onCameraPresetChange,
  onOpenVoiceNote,
}) => {
  // Internal state fallback if not controlled by parent
  const [internalShowGums, setInternalShowGums] = useState(true);
  const [internalIsolateTooth, setInternalIsolateTooth] = useState(false);
  const [internalArchMode, setInternalArchMode] = useState<ArchMode>('both');
  const [internalCameraPreset, setInternalCameraPreset] = useState<CameraPreset>('default');
  const [toothCenters, setToothCenters] = useState<Map<string, THREE.Vector3>>(new Map());

  const showGums = externalShowGums ?? internalShowGums;
  const setShowGums = (val: boolean) => {
    setInternalShowGums(val);
    onShowGumsChange?.(val);
  };

  const isolateTooth = externalIsolateTooth ?? internalIsolateTooth;
  const setIsolateTooth = (val: boolean) => {
    setInternalIsolateTooth(val);
    onIsolateToothChange?.(val);
  };

  const archMode = externalArchMode ?? internalArchMode;
  const setArchMode = (mode: ArchMode) => {
    setInternalArchMode(mode);
    onArchModeChange?.(mode);
  };

  const cameraPreset = externalCameraPreset ?? internalCameraPreset;
  const setCameraPreset = (p: CameraPreset) => {
    setInternalCameraPreset(p);
    onCameraPresetChange?.(p);
  };

  const controlsRef = useRef<any>(null);
  const isolatedToothPos = isolateTooth && selectedTooth ? toothCenters.get(selectedTooth) || null : null;

  return (
    <div
      className={`relative w-full h-full min-h-[450px] overflow-hidden ${
        cleanMode ? 'bg-transparent' : 'bg-gradient-to-b from-slate-950 via-slate-900 to-zinc-950 rounded-2xl shadow-2xl border border-slate-800/80'
      }`}
    >
      <Canvas camera={{ position: [0, 1.5, 7.5], fov: 45 }} shadows className="w-full h-full">
        <ambientLight intensity={0.8} />
        <directionalLight position={[5, 10, 7]} intensity={1.5} castShadow />
        <directionalLight position={[-5, -5, -5]} intensity={0.4} />
        <pointLight position={[0, 3, 2]} intensity={0.6} color="#ffffff" />

        <group>
          <GLTFBoundary
            fallback={
              <ProceduralDentalArch
                teethRecords={teethRecords}
                selectedTooth={selectedTooth}
                onSelectTooth={onSelectTooth}
                showGums={showGums}
                isolateTooth={isolateTooth}
                archMode={archMode}
              />
            }
          >
            <Suspense
              fallback={
                <ProceduralDentalArch
                  teethRecords={teethRecords}
                  selectedTooth={selectedTooth}
                  onSelectTooth={onSelectTooth}
                  showGums={showGums}
                  isolateTooth={isolateTooth}
                  archMode={archMode}
                />
              }
            >
              <GLTFDentalArch
                modelUrl={modelUrl}
                teethRecords={teethRecords}
                plannedProcedures={plannedProcedures}
                selectedTooth={selectedTooth}
                onSelectTooth={onSelectTooth}
                onDoubleClickTooth={onDoubleClickTooth}
                showGums={showGums}
                isolateTooth={isolateTooth}
                archMode={archMode}
                onOpenVoiceNote={onOpenVoiceNote}
                onToothCentersCalculated={setToothCenters}
              />
            </Suspense>
          </GLTFBoundary>
        </group>

        <CameraController
          preset={cameraPreset}
          controlsRef={controlsRef}
          isolatedToothPos={isolatedToothPos}
        />

        <OrbitControls
          ref={controlsRef}
          enablePan={false}
          minDistance={2}
          maxDistance={15}
          autoRotate={false}
          makeDefault
        />
      </Canvas>

      {/* Floating Clinical Toolbar Controls */}
      <div className="absolute top-3 left-3 z-20 flex flex-wrap items-center gap-2 max-w-[calc(100%-24px)]">
        {/* Teeth Only / Show Gums Toggle */}
        <button
          type="button"
          onClick={() => setShowGums(!showGums)}
          className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl border backdrop-blur-md shadow-lg transition-all ${
            showGums
              ? 'bg-slate-900/80 hover:bg-slate-800 text-slate-200 border-slate-700/80'
              : 'bg-emerald-600/90 hover:bg-emerald-500 text-white border-emerald-400'
          }`}
          title="Toggle gums to inspect teeth roots floating in 3D"
        >
          {showGums ? <Eye className="w-3.5 h-3.5" /> : <EyeOff className="w-3.5 h-3.5" />}
          <span>{showGums ? 'Gums Visible' : 'Teeth Only (Roots Exposed)'}</span>
        </button>

        {/* Solo Tooth Isolation Toggle */}
        <button
          type="button"
          disabled={!selectedTooth}
          onClick={() => setIsolateTooth(!isolateTooth)}
          className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl border backdrop-blur-md shadow-lg transition-all ${
            !selectedTooth
              ? 'bg-slate-900/40 text-slate-500 border-slate-800 cursor-not-allowed'
              : isolateTooth
              ? 'bg-amber-600/90 hover:bg-amber-500 text-white border-amber-400'
              : 'bg-slate-900/80 hover:bg-slate-800 text-slate-200 border-slate-700/80'
          }`}
          title={selectedTooth ? `Isolate Tooth #${selectedTooth}` : 'Select a tooth first to isolate'}
        >
          <Crosshair className="w-3.5 h-3.5" />
          <span>{isolateTooth ? `Solo Tooth #${selectedTooth}` : `Isolate #${selectedTooth || '--'}`}</span>
        </button>

        {/* Arch Segmented Controls */}
        <div className="flex items-center bg-slate-900/80 border border-slate-700/80 rounded-xl p-0.5 backdrop-blur-md shadow-lg text-xs">
          <button
            type="button"
            onClick={() => setArchMode('both')}
            className={`px-2.5 py-1 rounded-lg font-medium transition-colors ${
              archMode === 'both' ? 'bg-emerald-600 text-white shadow' : 'text-slate-300 hover:text-white'
            }`}
          >
            Both Arches
          </button>
          <button
            type="button"
            onClick={() => setArchMode('upper')}
            className={`px-2.5 py-1 rounded-lg font-medium transition-colors ${
              archMode === 'upper' ? 'bg-emerald-600 text-white shadow' : 'text-slate-300 hover:text-white'
            }`}
            title="Maxillary Arch (FDI 11-28)"
          >
            Upper (Maxilla)
          </button>
          <button
            type="button"
            onClick={() => setArchMode('lower')}
            className={`px-2.5 py-1 rounded-lg font-medium transition-colors ${
              archMode === 'lower' ? 'bg-emerald-600 text-white shadow' : 'text-slate-300 hover:text-white'
            }`}
            title="Mandibular Arch (FDI 31-48)"
          >
            Lower (Mandible)
          </button>
        </div>
      </div>

      {/* Camera Presets Toolbar (Bottom Left) */}
      <div className="absolute bottom-3 left-3 z-20 flex flex-wrap items-center gap-1.5 bg-slate-900/85 border border-slate-700/80 p-1.5 rounded-xl backdrop-blur-md shadow-xl text-[11px]">
        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-2 flex items-center gap-1">
          <Camera className="w-3 h-3 text-emerald-400" />
          Angles:
        </span>
        <button
          type="button"
          onClick={() => setCameraPreset('frontal')}
          className={`px-2 py-1 rounded-lg font-medium transition-colors ${
            cameraPreset === 'frontal' ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800'
          }`}
        >
          Frontal Smile
        </button>
        <button
          type="button"
          onClick={() => setCameraPreset('upper_occlusal')}
          className={`px-2 py-1 rounded-lg font-medium transition-colors ${
            cameraPreset === 'upper_occlusal' ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800'
          }`}
          title="Looking up at upper biting surfaces"
        >
          Upper Occlusal
        </button>
        <button
          type="button"
          onClick={() => setCameraPreset('lower_occlusal')}
          className={`px-2 py-1 rounded-lg font-medium transition-colors ${
            cameraPreset === 'lower_occlusal' ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800'
          }`}
          title="Looking down at lower biting surfaces"
        >
          Lower Occlusal
        </button>
        <button
          type="button"
          onClick={() => setCameraPreset('right_bite')}
          className={`px-2 py-1 rounded-lg font-medium transition-colors ${
            cameraPreset === 'right_bite' ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800'
          }`}
        >
          Right Bite
        </button>
        <button
          type="button"
          onClick={() => setCameraPreset('left_bite')}
          className={`px-2 py-1 rounded-lg font-medium transition-colors ${
            cameraPreset === 'left_bite' ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800'
          }`}
        >
          Left Bite
        </button>
      </div>
    </div>
  );
};

export default Dental3DViewer;
