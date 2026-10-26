import React, { Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { OrbitControls, useGLTF, Html } from '@react-three/drei';
import * as THREE from 'three';
import {
  ArchType,
  MeshPartType,
  NumberingSystem,
  ParsedMeshInfo,
  parseDentitionMesh,
  TEETH_MANIFEST,
  ToothSurfaceKey,
} from './dentitionManifest';
import {
  CONDITION_COLORS,
  CONDITION_LABELS,
  LayerVisibility,
  SURFACE_CONDITION_COLORS,
  SURFACE_CONDITION_LABELS,
  SurfaceCondition,
  ToothCondition,
  ToothRecord,
} from './types';
import {
  Eye,
  EyeOff,
  Layers,
  Camera,
  Crosshair,
  Sparkles,
  CheckCircle2,
} from 'lucide-react';

export type ArchMode = 'both' | 'upper' | 'lower';
export type CameraPreset = 'default' | 'frontal' | 'upper_occlusal' | 'lower_occlusal' | 'right_bite' | 'left_bite';

export interface Dental3DViewerProps {
  teethRecords: Record<string, ToothRecord>;
  plannedProcedures?: Record<string, any[]>;
  selectedTooth: string | null;
  onSelectTooth: (toothNumber: string) => void;
  onSelectSurface?: (toothNumber: string, surfaceKey: ToothSurfaceKey) => void;
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

  // Clinical Capabilities
  xrayMode?: boolean;
  onXrayModeChange?: (xray: boolean) => void;
  layers?: LayerVisibility;
  onLayersChange?: (layers: LayerVisibility) => void;
  numberingSystem?: NumberingSystem;
  onNumberingSystemChange?: (sys: NumberingSystem) => void;

  onOpenVoiceNote?: (toothNumber: string, procedure?: any) => void;
}

// Camera Presets configured for natural dental perspective (telephoto FOV, no fish-eye distortion)
const CAMERA_PRESET_CONFIGS: Record<CameraPreset, { pos: [number, number, number]; target: [number, number, number] }> = {
  default: { pos: [0, 1.4, 8.8], target: [0, 0, 0] },
  frontal: { pos: [0, 0.3, 8.8], target: [0, 0, 0] },
  upper_occlusal: { pos: [0, -6.5, 2.2], target: [0, 1.6, 0.8] },
  lower_occlusal: { pos: [0, 6.5, 2.0], target: [0, -1.4, 0.8] },
  right_bite: { pos: [8.8, 0.5, 0.6], target: [0, 0, 0] },
  left_bite: { pos: [-8.8, 0.5, 0.6], target: [0, 0, 0] },
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
        isolatedToothPos.x * 1.25,
        isolatedToothPos.y + 0.35,
        isolatedToothPos.z + 4.8
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
  onSelectSurface?: (toothNumber: string, surfaceKey: ToothSurfaceKey) => void;
  onDoubleClickTooth?: (toothNumber: string) => void;
  showGums: boolean;
  isolateTooth: boolean;
  archMode: ArchMode;
  xrayMode: boolean;
  layers: LayerVisibility;
  numberingSystem: NumberingSystem;
  onOpenVoiceNote?: (toothNumber: string, procedure?: any) => void;
  onToothCentersCalculated?: (map: Map<string, THREE.Vector3>) => void;
}> = ({
  modelUrl,
  teethRecords,
  plannedProcedures,
  selectedTooth,
  onSelectTooth,
  onSelectSurface,
  onDoubleClickTooth,
  showGums,
  isolateTooth,
  archMode,
  xrayMode,
  layers,
  numberingSystem,
  onOpenVoiceNote,
  onToothCentersCalculated,
}) => {
  const gltf = useGLTF(modelUrl) as any;
  const [hoveredTooth, setHoveredTooth] = useState<string | null>(null);
  const [hoveredSurface, setHoveredSurface] = useState<ParsedMeshInfo | null>(null);
  const [hoveredPos, setHoveredPos] = useState<THREE.Vector3 | null>(null);

  const hoverLeaveTimeoutRef = useRef<any>(null);
  const lastClickTimeRef = useRef<number>(0);
  const lastClickToothRef = useRef<string | null>(null);

  useEffect(() => {
    return () => {
      if (hoverLeaveTimeoutRef.current) {
        clearTimeout(hoverLeaveTimeoutRef.current);
      }
    };
  }, []);

  // Clone scene & index meshes by dental anatomical identity
  const { scene, meshInfoMap, toothCenters } = useMemo(() => {
    if (!gltf || !gltf.scene) {
      return { scene: new THREE.Group(), meshInfoMap: new Map<string, ParsedMeshInfo>(), toothCenters: new Map() };
    }

    const cloned = gltf.scene.clone(true);
    const infoMap = new Map<string, ParsedMeshInfo>();
    const centers = new Map<string, THREE.Vector3>();
    const toothMeshAccumulators = new Map<string, { count: number; sum: THREE.Vector3 }>();

    cloned.traverse((child: any) => {
      if (child.isMesh) {
        const dentalInfo = parseDentitionMesh(child.name);
        if (dentalInfo) {
          infoMap.set(child.uuid, dentalInfo);
          child.userData.dental = dentalInfo;
        }

        if (child.material) {
          child.material = child.material.clone();
        } else {
          child.material = new THREE.MeshStandardMaterial();
        }

        // Store authored original material properties from the GLTF file
        child.userData.originalMaterial = {
          color: child.material.color ? child.material.color.clone() : new THREE.Color(1, 1, 1),
          roughness: child.material.roughness ?? 0.2,
          metalness: child.material.metalness ?? 0.02,
          vertexColors: child.material.vertexColors ?? true,
          opacity: child.material.opacity ?? 1.0,
          transparent: child.material.transparent ?? false,
        };

        child.castShadow = true;
        child.receiveShadow = true;
      }
    });

    // Auto-fit scale & center around origin
    const box = new THREE.Box3().setFromObject(cloned);
    const size = new THREE.Vector3();
    const center = new THREE.Vector3();
    box.getSize(size);
    box.getCenter(center);
    const maxDim = Math.max(size.x, size.y, size.z);

    if (maxDim > 0) {
      const scaleFactor = 5.4 / maxDim;
      cloned.scale.set(scaleFactor, scaleFactor, scaleFactor);
      cloned.position.set(-center.x * scaleFactor, -center.y * scaleFactor, -center.z * scaleFactor);
    }

    // Recalculate centers after scaling
    cloned.traverse((child: any) => {
      if (child.isMesh) {
        const info = infoMap.get(child.uuid);
        if (info && info.toothNumber) {
          const childBox = new THREE.Box3().setFromObject(child);
          const childCenter = new THREE.Vector3();
          childBox.getCenter(childCenter);

          const acc = toothMeshAccumulators.get(info.toothNumber) || { count: 0, sum: new THREE.Vector3() };
          acc.count++;
          acc.sum.add(childCenter);
          toothMeshAccumulators.set(info.toothNumber, acc);
        }
      }
    });

    toothMeshAccumulators.forEach((acc, num) => {
      centers.set(num, acc.sum.multiplyScalar(1 / acc.count));
    });

    return { scene: cloned, meshInfoMap: infoMap, toothCenters: centers };
  }, [gltf]);

  useEffect(() => {
    if (onToothCentersCalculated) {
      onToothCentersCalculated(toothCenters);
    }
  }, [toothCenters, onToothCentersCalculated]);

  // Dynamic Visibility Engine (Arch mode, Isolation, Layers, X-Ray)
  useEffect(() => {
    scene.traverse((child: any) => {
      if (child.isMesh) {
        // Cache original raycast function if not cached
        if (!child.userData.origRaycast) {
          child.userData.origRaycast = child.raycast;
        }

        const dental = meshInfoMap.get(child.uuid) as ParsedMeshInfo | undefined;
        if (!dental) {
          if (isolateTooth) {
            child.visible = false;
            child.raycast = () => null;
          }
          return;
        }

        const toothNum = dental.toothNumber;

        // Arch filter
        if (archMode === 'upper' && dental.arch !== 'Maxillary') {
          child.visible = false;
          child.raycast = () => null;
          return;
        }
        if (archMode === 'lower' && dental.arch !== 'Mandibular') {
          child.visible = false;
          child.raycast = () => null;
          return;
        }

        // Solo tooth isolation: 100% guarantee other teeth cannot be seen or raycasted
        if (isolateTooth && toothNum !== selectedTooth) {
          child.visible = false;
          child.raycast = () => null;
          return;
        }

        // Layer visibility: In normal view, internal layers are completely hidden and non-raycastable!
        if (dental.isCrown && !layers.crowns) {
          child.visible = false;
          child.raycast = () => null;
          return;
        }
        if (dental.isRoot && !layers.roots && !xrayMode) {
          child.visible = false;
          child.raycast = () => null;
          return;
        }
        if (dental.isDentin && !layers.dentin && !xrayMode) {
          child.visible = false;
          child.raycast = () => null;
          return;
        }
        if (dental.isPulp && !layers.pulp && !xrayMode) {
          child.visible = false;
          child.raycast = () => null;
          return;
        }

        child.visible = true;
        child.raycast = child.userData.origRaycast || child.raycast;
      }
    });
  }, [scene, meshInfoMap, isolateTooth, selectedTooth, archMode, layers, xrayMode]);

  // PBR Anatomical Material & Surface Shading Engine
  useEffect(() => {
    scene.traverse((child: any) => {
      if (child.isMesh && child.visible) {
        const dental = meshInfoMap.get(child.uuid) as ParsedMeshInfo | undefined;
        if (!dental) return;

        const toothNum = dental.toothNumber;
        const record = teethRecords[toothNum];
        const procs = plannedProcedures?.[toothNum] || [];
        const hasPlanned = procs.some((p: any) => p.status === 'planned' || p.status === 'in_progress');
        const hasCompleted = procs.some((p: any) => p.status === 'completed');
        const mainCondition = record?.condition || (hasCompleted ? 'composite_filled' : 'healthy');

        const isToothSelected = selectedTooth === toothNum;
        const isToothHovered = hoveredTooth === toothNum;
        const isThisSurfaceHovered = isToothHovered && hoveredSurface?.surfaceKey === dental.surfaceKey;

        const mat = child.material as THREE.MeshStandardMaterial;
        const orig = child.userData.originalMaterial || {
          color: new THREE.Color(1, 1, 1),
          roughness: 0.2,
          metalness: 0.02,
          vertexColors: true,
          opacity: 1.0,
          transparent: false,
        };

        // Reset base flags and restore exact authored GLTF properties
        mat.map = null;
        mat.wireframe = false;
        mat.depthWrite = true;
        mat.color.copy(orig.color);
        mat.roughness = orig.roughness;
        mat.metalness = orig.metalness;
        mat.vertexColors = orig.vertexColors;
        mat.transparent = orig.transparent;
        mat.opacity = orig.opacity;
        mat.emissive.set('#000000');
        mat.emissiveIntensity = 0;

        // Missing tooth handling
        if (mainCondition === 'missing') {
          mat.color.set('#64748B');
          mat.transparent = true;
          mat.opacity = 0.16;
          mat.wireframe = true;
          mat.needsUpdate = true;
          return;
        }

        // Implant handling
        if (mainCondition === 'implant') {
          mat.color.set('#06B6D4');
          mat.metalness = 0.85;
          mat.roughness = 0.2;
          mat.transparent = false;
          mat.opacity = 1.0;
          if (isToothSelected) {
            mat.emissive.set('#10B981');
            mat.emissiveIntensity = 0.45;
          }
          mat.needsUpdate = true;
          return;
        }

        // Full prosthetic crown handling
        if (mainCondition === 'crown') {
          mat.color.set('#F59E0B');
          mat.metalness = 0.35;
          mat.roughness = 0.22;
          mat.transparent = false;
          mat.opacity = 1.0;
          if (isToothSelected) {
            mat.emissive.set('#10B981');
            mat.emissiveIntensity = 0.45;
          }
          mat.needsUpdate = true;
          return;
        }

        // X-Ray Mode handling
        if (xrayMode) {
          if (dental.isPulp) {
            const hasRct = mainCondition === 'root_canal' || procs.some((p: any) => p.procedure_code?.category === 'endodontics');
            mat.color.set(hasRct ? '#8B5CF6' : '#E11D48');
            mat.emissive.set(hasRct ? '#8B5CF6' : '#BE123C');
            mat.emissiveIntensity = 1.2;
            mat.transparent = false;
            mat.opacity = 1.0;
          } else if (dental.isDentin) {
            mat.transparent = true;
            mat.opacity = 0.35;
            mat.depthWrite = false;
          } else if (dental.isRoot) {
            mat.transparent = true;
            mat.opacity = 0.32;
            mat.depthWrite = false;
            mat.emissive.set('#38BDF8');
            mat.emissiveIntensity = 0.15;
          } else if (dental.isCrown) {
            mat.transparent = true;
            mat.opacity = 0.26;
            mat.depthWrite = false;
            mat.emissive.set('#93C5FD');
            mat.emissiveIntensity = 0.2;
          }
          mat.needsUpdate = true;
          return;
        }

        // Standard / Exact File View
        if (dental.isCrown) {
          const surfaceKey = dental.surfaceKey;
          const surfaceCondition: SurfaceCondition = surfaceKey && record?.surfaces?.[surfaceKey]
            ? record.surfaces[surfaceKey]!
            : 'healthy';

          if (surfaceCondition !== 'healthy') {
            mat.color.set(SURFACE_CONDITION_COLORS[surfaceCondition] || orig.color);
            if (surfaceCondition === 'amalgam') {
              mat.roughness = 0.28;
              mat.metalness = 0.75;
            }
          } else if (mainCondition === 'active_caries') {
            mat.color.set(CONDITION_COLORS.active_caries);
          } else if (mainCondition === 'composite_filled') {
            mat.color.set(CONDITION_COLORS.composite_filled);
          }
          // Note: If healthy, mat.color remains orig.color (with orig.vertexColors) - exactly as in GLTF!
        }

        // Selection, Hover & Treatment Plan Emissive Highlights with crisp layer delineation
        if (isThisSurfaceHovered) {
          mat.emissive.set('#38BDF8');
          mat.emissiveIntensity = 0.7;
        } else if (isToothSelected) {
          mat.emissive.set('#10B981');
          mat.emissiveIntensity = 0.38;
        } else if (isToothHovered) {
          // Subtle glow on other parts of the tooth so the specific hovered surface/layer border is razor-sharp
          mat.emissive.set('#60A5FA');
          mat.emissiveIntensity = 0.12;
        } else if (hasPlanned) {
          mat.emissive.set('#8B5CF6');
          mat.emissiveIntensity = 0.3;
        }

        mat.needsUpdate = true;
      }
    });
  }, [scene, meshInfoMap, teethRecords, plannedProcedures, selectedTooth, hoveredTooth, hoveredSurface, xrayMode]);

  const activeHoverProcedures = hoveredTooth ? plannedProcedures?.[hoveredTooth] || [] : [];
  const hoveredManifest = hoveredTooth ? TEETH_MANIFEST[hoveredTooth] : null;

  return (
    <group>
      <primitive
        object={scene}
        onPointerDown={(e: any) => {
          e.stopPropagation();
          const info = meshInfoMap.get(e.object?.uuid);
          if (!info || !info.toothNumber || !e.object?.visible) return;
          if (isolateTooth && selectedTooth && info.toothNumber !== selectedTooth) return;

          onSelectTooth(info.toothNumber);

          // If a specific crown surface was clicked, notify surface selection
          if (info.surfaceKey && onSelectSurface) {
            onSelectSurface(info.toothNumber, info.surfaceKey);
          }

          // Double click handler
          const now = performance.now();
          if (lastClickToothRef.current === info.toothNumber && now - lastClickTimeRef.current < 450) {
            onDoubleClickTooth?.(info.toothNumber);
            lastClickTimeRef.current = 0;
            lastClickToothRef.current = null;
          } else {
            lastClickTimeRef.current = now;
            lastClickToothRef.current = info.toothNumber;
          }
        }}
        onPointerOver={(e: any) => {
          e.stopPropagation();
          const info = meshInfoMap.get(e.object?.uuid);
          if (!info || !info.toothNumber || !e.object?.visible) return;
          if (isolateTooth && selectedTooth && info.toothNumber !== selectedTooth) return;

          if (hoverLeaveTimeoutRef.current) {
            clearTimeout(hoverLeaveTimeoutRef.current);
            hoverLeaveTimeoutRef.current = null;
          }

          setHoveredTooth(info.toothNumber);
          setHoveredSurface(info);
          const center = toothCenters.get(info.toothNumber);
          setHoveredPos(center ? center.clone() : e.point.clone());
        }}
        onPointerMove={(e: any) => {
          e.stopPropagation();
          const info = meshInfoMap.get(e.object?.uuid);
          if (!info || !info.toothNumber || !e.object?.visible) return;
          if (isolateTooth && selectedTooth && info.toothNumber !== selectedTooth) return;

          if (hoverLeaveTimeoutRef.current) {
            clearTimeout(hoverLeaveTimeoutRef.current);
            hoverLeaveTimeoutRef.current = null;
          }

          if (hoveredTooth !== info.toothNumber) {
            setHoveredTooth(info.toothNumber);
            const center = toothCenters.get(info.toothNumber);
            setHoveredPos(center ? center.clone() : e.point.clone());
          }
          if (hoveredSurface?.surfaceKey !== info.surfaceKey || hoveredSurface?.partType !== info.partType) {
            setHoveredSurface(info);
          }
        }}
        onPointerOut={(e: any) => {
          e.stopPropagation();
          if (hoverLeaveTimeoutRef.current) {
            clearTimeout(hoverLeaveTimeoutRef.current);
          }
          hoverLeaveTimeoutRef.current = setTimeout(() => {
            setHoveredTooth(null);
            setHoveredSurface(null);
            setHoveredPos(null);
          }, 140);
        }}
      />

      {/* Enhanced Clinical Surface Tooltip: Non-interactive to mouse rays so it NEVER glitches or flickers */}
      {hoveredTooth && hoveredPos && (!isolateTooth || hoveredTooth === selectedTooth) && (
        <Html
          position={[hoveredPos.x, hoveredPos.y + 0.65, hoveredPos.z]}
          center
          style={{ pointerEvents: 'none', zIndex: 50 }}
        >
          <div
            className="pointer-events-none select-none bg-slate-900/95 text-slate-100 p-3.5 rounded-2xl shadow-2xl min-w-[240px] max-w-[300px] border border-slate-700/80 backdrop-blur-md flex flex-col gap-2 text-xs transition-opacity duration-150 ring-1 ring-white/10"
          >
            {/* Header: Tooth number in FDI & Universal */}
            <div className="flex items-center justify-between border-b border-slate-800 pb-2">
              <div className="flex flex-col">
                <span className="font-bold text-sm text-white">
                  Tooth #{numberingSystem === 'universal' ? (hoveredManifest?.universalNumber || hoveredTooth) : hoveredTooth}
                </span>
                <span className="text-[10px] text-slate-400">
                  {numberingSystem === 'universal' ? `FDI #${hoveredTooth}` : `UN ${hoveredManifest?.universalNumber || ''}`}
                  {' · '}{hoveredManifest?.quadrant} ({hoveredManifest?.arch})
                </span>
              </div>
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

            {/* Anatomical Name */}
            {hoveredManifest && (
              <div className="text-[11px] font-medium text-emerald-400">
                {hoveredManifest.clinicalName}
              </div>
            )}

            {/* Hovered Surface Badge */}
            {hoveredSurface?.surfaceKey && (
              <div className="flex items-center justify-between bg-slate-950/80 p-1.5 rounded-lg border border-slate-800">
                <span className="text-[11px] font-medium text-slate-300">
                  📍 {hoveredSurface.surfaceLabel || hoveredSurface.surfaceKey}
                </span>
                {(() => {
                  const sCond = teethRecords[hoveredTooth]?.surfaces?.[hoveredSurface.surfaceKey!] || 'healthy';
                  return (
                    <span
                      className="text-[9px] font-bold px-1.5 py-0.5 rounded"
                      style={{
                        backgroundColor: `${SURFACE_CONDITION_COLORS[sCond]}25`,
                        color: SURFACE_CONDITION_COLORS[sCond],
                      }}
                    >
                      {SURFACE_CONDITION_LABELS[sCond]?.label || sCond}
                    </span>
                  );
                })()}
              </div>
            )}

            {/* Notes if any */}
            {teethRecords[hoveredTooth]?.notes && (
              <div className="text-[11px] text-slate-300 italic">
                "{teethRecords[hoveredTooth].notes}"
              </div>
            )}

            {/* Medical Procedures Details */}
            {activeHoverProcedures.length > 0 && (
              <div className="flex flex-col gap-1.5 pt-1 border-t border-slate-800">
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
                      {proc.anesthetic_agent && (
                        <div className="text-[10px] text-emerald-300">
                          💉 {proc.anesthetic_agent} ({proc.anesthetic_dosage || '1 carpule'})
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}

            <div className="text-[9px] text-slate-500 text-center pt-1">
              Click to select surface · Double-click for 3D inspection
            </div>
          </div>
        </Html>
      )}
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
    console.warn('GLTF load failed, switching to fallback:', error);
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
  onSelectSurface,
  onDoubleClickTooth,
  modelUrl = '/models/Dentition_Permanent_Full_V2.1.glb',
  cleanMode = false,
  showGums: externalShowGums,
  onShowGumsChange,
  isolateTooth: externalIsolateTooth,
  onIsolateToothChange,
  archMode: externalArchMode,
  onArchModeChange,
  cameraPreset: externalCameraPreset,
  onCameraPresetChange,
  xrayMode: externalXrayMode,
  onXrayModeChange,
  layers: externalLayers,
  onLayersChange,
  numberingSystem: externalNumberingSystem,
  onNumberingSystemChange,
  onOpenVoiceNote,
}) => {
  // Internal state fallbacks: Roots are HIDDEN by default to prevent spike appearance
  const [internalShowGums, setInternalShowGums] = useState(false);
  const [internalIsolateTooth, setInternalIsolateTooth] = useState(false);
  const [internalArchMode, setInternalArchMode] = useState<ArchMode>('both');
  const [internalCameraPreset, setInternalCameraPreset] = useState<CameraPreset>('default');
  const [internalXrayMode, setInternalXrayMode] = useState(false);
  const [internalLayers, setInternalLayers] = useState<LayerVisibility>({
    crowns: true,
    roots: true,
    dentin: true,
    pulp: true,
  });
  const [internalNumberingSystem, setInternalNumberingSystem] = useState<NumberingSystem>('fdi');
  const [showLayerMenu, setShowLayerMenu] = useState(false);

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

  const xrayMode = externalXrayMode ?? internalXrayMode;
  const setXrayMode = (x: boolean) => {
    setInternalXrayMode(x);
    onXrayModeChange?.(x);
  };

  const layers = externalLayers ?? internalLayers;
  const setLayers = (l: LayerVisibility) => {
    setInternalLayers(l);
    onLayersChange?.(l);
  };

  const numberingSystem = externalNumberingSystem ?? internalNumberingSystem;
  const setNumberingSystem = (sys: NumberingSystem) => {
    setInternalNumberingSystem(sys);
    onNumberingSystemChange?.(sys);
  };

  const toggleLayer = (key: keyof LayerVisibility) => {
    const next = { ...layers, [key]: !layers[key] };
    setLayers(next);
  };

  const controlsRef = useRef<any>(null);
  const isolatedToothPos = isolateTooth && selectedTooth ? toothCenters.get(selectedTooth) || null : null;

  return (
    <div className="relative w-full h-full min-h-[480px] overflow-hidden bg-gradient-to-b from-slate-950 via-slate-900 to-zinc-950 rounded-2xl shadow-2xl border border-slate-800/80">
      <Canvas camera={{ position: [0, 1.4, 8.8], fov: 32 }} shadows className="w-full h-full">
        {/* Realistic Dental Studio Illumination */}
        <hemisphereLight args={['#ffffff', '#1e293b', 1.1]} />
        <directionalLight position={[4, 6, 8]} intensity={2.2} castShadow />
        <directionalLight position={[-4, -2, 6]} intensity={1.3} />
        <directionalLight position={[0, -6, -4]} intensity={1.0} />
        {xrayMode && <pointLight position={[0, 0, 0]} intensity={1.5} color="#38BDF8" />}

        <group>
          <GLTFBoundary
            fallback={
              <Html center>
                <div className="p-4 rounded-xl bg-slate-900 text-rose-400 border border-slate-700">
                  Model render fallback
                </div>
              </Html>
            }
          >
            <Suspense
              fallback={
                <Html center>
                  <div className="flex flex-col items-center gap-3 p-4 rounded-2xl bg-slate-900/90 border border-slate-700/80 backdrop-blur-md shadow-2xl text-slate-100 min-w-[220px]">
                    <div className="w-7 h-7 border-3 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                    <span className="text-xs font-bold tracking-wide text-emerald-400">Loading High-Res 3D Dentition...</span>
                    <span className="text-[10px] text-slate-400">32 Discrete Anatomical Teeth</span>
                  </div>
                </Html>
              }
            >
              <GLTFDentalArch
                modelUrl={modelUrl}
                teethRecords={teethRecords}
                plannedProcedures={plannedProcedures}
                selectedTooth={selectedTooth}
                onSelectTooth={onSelectTooth}
                onSelectSurface={onSelectSurface}
                onDoubleClickTooth={onDoubleClickTooth}
                showGums={showGums}
                isolateTooth={isolateTooth}
                archMode={archMode}
                xrayMode={xrayMode}
                layers={layers}
                numberingSystem={numberingSystem}
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
          minDistance={2.5}
          maxDistance={18}
          autoRotate={false}
          makeDefault
        />
      </Canvas>

      {/* Primary Clinical Control Toolbar (Top Left) */}
      <div className="absolute top-3 left-3 z-20 flex flex-wrap items-center gap-2 max-w-[calc(100%-24px)]">
        {/* X-Ray / Endodontic Mode Button */}
        <button
          type="button"
          onClick={() => setXrayMode(!xrayMode)}
          className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl border backdrop-blur-md shadow-lg transition-all ${
            xrayMode
              ? 'bg-sky-500/90 hover:bg-sky-400 text-white border-sky-300 ring-2 ring-sky-400/50 shadow-sky-500/30'
              : 'bg-slate-900/80 hover:bg-slate-800 text-slate-200 border-slate-700/80'
          }`}
          title="Toggle X-Ray translucent view to inspect pulp chambers, root canals, and internal anatomy"
        >
          <Sparkles className={`w-3.5 h-3.5 ${xrayMode ? 'animate-pulse text-white' : 'text-sky-400'}`} />
          <span>{xrayMode ? 'X-Ray / Endo Active' : 'X-Ray View'}</span>
        </button>

        {/* Anatomical Layer Toggles Dropdown */}
        <div className="relative">
          <button
            type="button"
            onClick={() => setShowLayerMenu(!showLayerMenu)}
            className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl border backdrop-blur-md shadow-lg bg-slate-900/80 hover:bg-slate-800 text-slate-200 border-slate-700/80 transition-all"
            title="Toggle anatomical layers (Crowns, Roots, Dentin, Pulp)"
          >
            <Layers className="w-3.5 h-3.5 text-emerald-400" />
            <span>Layers</span>
          </button>

          {showLayerMenu && (
            <div className="absolute top-full left-0 mt-2 w-48 p-2 rounded-xl bg-slate-900/95 border border-slate-700/90 shadow-2xl backdrop-blur-md flex flex-col gap-1 z-30">
              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-2 py-1">
                Anatomy Visibility:
              </span>
              {[
                { key: 'crowns', label: 'Enamel Crowns' },
                { key: 'roots', label: 'Roots (Cementum)' },
                { key: 'dentin', label: 'Internal Dentin' },
                { key: 'pulp', label: 'Pulp & Canals' },
              ].map(({ key, label }) => {
                const k = key as keyof LayerVisibility;
                const active = layers[k];
                return (
                  <button
                    key={key}
                    type="button"
                    onClick={() => toggleLayer(k)}
                    className={`flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                      active ? 'bg-emerald-600/30 text-emerald-300' : 'text-slate-400 hover:bg-slate-800'
                    }`}
                  >
                    <span>{label}</span>
                    {active ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" /> : <EyeOff className="w-3.5 h-3.5 text-slate-500" />}
                  </button>
                );
              })}
            </div>
          )}
        </div>

        {/* Numbering System Toggle */}
        <button
          type="button"
          onClick={() => setNumberingSystem(numberingSystem === 'fdi' ? 'universal' : 'fdi')}
          className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl border backdrop-blur-md shadow-lg bg-slate-900/80 hover:bg-slate-800 text-slate-200 border-slate-700/80 transition-all"
          title="Switch between FDI (11-48) and Universal (#01-#32) numbering"
        >
          <span className="text-amber-400 font-bold text-[11px]">{numberingSystem.toUpperCase()}</span>
          <span className="text-[11px] text-slate-300">
            {numberingSystem === 'fdi' ? 'FDI (11-48)' : 'Universal (#1-32)'}
          </span>
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
          Views:
        </span>
        <button
          type="button"
          onClick={() => setCameraPreset('frontal')}
          className={`px-2 py-1 rounded-lg font-medium transition-colors ${
            cameraPreset === 'frontal' ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800'
          }`}
        >
          Frontal
        </button>
        <button
          type="button"
          onClick={() => setCameraPreset('upper_occlusal')}
          className={`px-2 py-1 rounded-lg font-medium transition-colors ${
            cameraPreset === 'upper_occlusal' ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800'
          }`}
          title="Upper biting surfaces"
        >
          Upper Occlusal
        </button>
        <button
          type="button"
          onClick={() => setCameraPreset('lower_occlusal')}
          className={`px-2 py-1 rounded-lg font-medium transition-colors ${
            cameraPreset === 'lower_occlusal' ? 'bg-emerald-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800'
          }`}
          title="Lower biting surfaces"
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
