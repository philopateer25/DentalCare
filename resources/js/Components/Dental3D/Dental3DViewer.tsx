import React, { Suspense, useEffect, useMemo, useState } from 'react';
import { Canvas } from '@react-three/fiber';
import { OrbitControls, Center, Bounds, useGLTF, Html } from '@react-three/drei';
import * as THREE from 'three';
import InteractiveTooth from './InteractiveTooth';
import { CONDITION_COLORS, CONDITION_LABELS, ToothRecord, ToothCondition } from './types';

interface Dental3DViewerProps {
  teethRecords: Record<string, ToothRecord>;
  selectedTooth: string | null;
  onSelectTooth: (toothNumber: string) => void;
  onDoubleClickTooth?: (toothNumber: string) => void;
  modelUrl?: string;
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

// Check if node is soft tissue (gums, jaw, tongue, etc.)
export function isSoftTissueNode(nodeName: string): boolean {
  const lower = nodeName.toLowerCase();
  return (
    lower.includes('gum') ||
    lower.includes('gingiva') ||
    lower.includes('jaw') ||
    lower.includes('tongue') ||
    lower.includes('mouth') ||
    lower.includes('base') ||
    lower.includes('soft') ||
    lower.includes('wet') ||
    lower.includes('object_4') ||
    lower.includes('object_8')
  );
}

// Check if node is a structural/bounding box artifact (like Blender's default Cube)
export function isIgnoredNode(nodeName: string): boolean {
  const lower = nodeName.toLowerCase();
  return lower.includes('cube') || lower.includes('bounds');
}

// GLTF Dental Arch Model Component
const GLTFDentalArch: React.FC<{
  modelUrl: string;
  teethRecords: Record<string, ToothRecord>;
  selectedTooth: string | null;
  onSelectTooth: (toothNumber: string) => void;
  onDoubleClickTooth?: (toothNumber: string) => void;
}> = ({ modelUrl, teethRecords, selectedTooth, onSelectTooth, onDoubleClickTooth }) => {
  const gltf = useGLTF(modelUrl) as any;
  const [hoveredTooth, setHoveredTooth] = useState<string | null>(null);
  const [hoveredPos, setHoveredPos] = useState<THREE.Vector3 | null>(null);

  // Step 1: Automated Diagnostic Check Logger
  useEffect(() => {
    if (gltf) {
      console.group(`=== 3D Model Diagnostic Report [${modelUrl}] ===`);
      console.log('Total Nodes:', Object.keys(gltf.nodes || {}));
      console.log('Total Materials:', Object.keys(gltf.materials || {}));

      try {
        const box = new THREE.Box3().setFromObject(gltf.scene);
        const size = new THREE.Vector3();
        const center = new THREE.Vector3();
        box.getSize(size);
        box.getCenter(center);
        console.log('Model Real Bounding Box Size:', size);
        console.log('Model Real Center Offset:', center);
      } catch (err) {
        console.warn('Could not compute bounding box:', err);
      }

      console.groupEnd();
    }
  }, [gltf, modelUrl]);

  // Step 2 & 3: Clone scene to preserve world transforms and prepare unique materials
  const { scene, nodeMap } = useMemo(() => {
    if (!gltf || !gltf.scene) return { scene: new THREE.Group(), nodeMap: new Map() };

    const cloned = gltf.scene.clone(true);
    const map = new Map<string, string>();
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
          return; // Skip material cloning and mapping for hidden artifacts
        }

        // Clone material to allow independent color updates
        if (child.material) {
          child.material = child.material.clone();
        } else {
          child.material = new THREE.MeshStandardMaterial();
        }

        if (isSoftTissueNode(child.name)) {
          // Disable raycasting for gums/tongue so they don't block teeth
          child.raycast = () => null;
        } else {
          // It's a tooth mesh
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

    // Completely remove artifact meshes so they don't affect sizing
    nodesToRemove.forEach(node => node.removeFromParent());

    // Auto-scale the cleaned model to always be roughly 6 units wide
    // This perfectly fits our default camera at [0, 1.5, 7.5] without needing Bounds
    const box = new THREE.Box3().setFromObject(cloned);
    const size = new THREE.Vector3();
    box.getSize(size);
    const maxDim = Math.max(size.x, size.y, size.z);
    
    if (maxDim > 0) {
      const scaleFactor = 6.5 / maxDim;
      cloned.scale.set(scaleFactor, scaleFactor, scaleFactor);
    }

    return { scene: cloned, nodeMap: map };
  }, [gltf]);

  // Apply dynamic condition colors and styles
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
            const condition = record?.condition || 'healthy';
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
  }, [scene, nodeMap, teethRecords, selectedTooth, hoveredTooth]);

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
      {hoveredTooth && hoveredPos && (
        <Html position={hoveredPos} center style={{ pointerEvents: 'none', zIndex: 10 }}>
          <div className="bg-slate-900/95 text-slate-100 px-3 py-2 rounded-lg shadow-2xl max-w-[200px] border border-slate-700 backdrop-blur-sm whitespace-pre-wrap flex flex-col gap-1">
            <div className="flex items-center justify-between">
              <span className="font-bold text-sm">Tooth #{hoveredTooth}</span>
            </div>
            <div className="flex items-center gap-1.5 mt-0.5">
              <span
                className="w-2 h-2 rounded-full"
                style={{ backgroundColor: CONDITION_COLORS[teethRecords[hoveredTooth]?.condition || 'healthy'] }}
              />
              <span className="text-xs text-slate-300">
                {CONDITION_LABELS[teethRecords[hoveredTooth]?.condition || 'healthy']?.label}
              </span>
            </div>
            {teethRecords[hoveredTooth]?.condition === 'custom' && teethRecords[hoveredTooth]?.notes && (
              <div className="text-xs text-rose-400 mt-1 italic">
                "{teethRecords[hoveredTooth].notes}"
              </div>
            )}
            <div className="text-[9px] text-slate-500 uppercase mt-1.5 font-semibold">
              Double-click for details
            </div>
          </div>
        </Html>
      )}
    </group>
  );
};

// Procedural Dental Arch Component (fallback if GLTF model is loading or missing)
const ProceduralDentalArch: React.FC<{
  teethRecords: Record<string, ToothRecord>;
  selectedTooth: string | null;
  onSelectTooth: (toothNumber: string) => void;
}> = ({ teethRecords, selectedTooth, onSelectTooth }) => {
  const toothNumbers = useMemo(() => {
    const list: Array<{ number: string; pos: [number, number, number]; rot: [number, number, number] }> = [];

    // Maxillary Arch (Upper: 18..11, 21..28)
    const upperTeeth = ['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'];
    const totalUpper = upperTeeth.length;
    upperTeeth.forEach((num, idx) => {
      const angle = Math.PI * 0.15 + (idx / (totalUpper - 1)) * Math.PI * 0.7;
      const radiusX = 2.4;
      const radiusZ = 2.8;
      const x = Math.cos(angle) * radiusX;
      const z = Math.sin(angle) * radiusZ - 1.2;
      const y = 0.6;
      list.push({
        number: num,
        pos: [x, y, z],
        rot: [0, -angle + Math.PI / 2, 0],
      });
    });

    // Mandibular Arch (Lower: 48..41, 31..38)
    const lowerTeeth = ['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'];
    const totalLower = lowerTeeth.length;
    lowerTeeth.forEach((num, idx) => {
      const angle = Math.PI * 0.15 + (idx / (totalLower - 1)) * Math.PI * 0.7;
      const radiusX = 2.25;
      const radiusZ = 2.6;
      const x = Math.cos(angle) * radiusX;
      const z = Math.sin(angle) * radiusZ - 1.2;
      const y = -0.6;
      list.push({
        number: num,
        pos: [x, y, z],
        rot: [Math.PI, -angle + Math.PI / 2, 0],
      });
    });

    return list;
  }, []);

  const toothGeometry = useMemo(() => {
    return new THREE.CapsuleGeometry(0.22, 0.45, 8, 16);
  }, []);

  return (
    <group>
      {/* Upper Arch Gum Base */}
      <mesh position={[0, 0.75, -0.2]} rotation={[Math.PI / 2, 0, 0]} raycast={() => null}>
        <torusGeometry args={[2.3, 0.35, 16, 32, Math.PI * 0.8]} />
        <meshStandardMaterial color="#E18A8A" roughness={0.6} />
      </mesh>

      {/* Lower Arch Gum Base */}
      <mesh position={[0, -0.75, -0.2]} rotation={[Math.PI / 2, 0, 0]} raycast={() => null}>
        <torusGeometry args={[2.15, 0.35, 16, 32, Math.PI * 0.8]} />
        <meshStandardMaterial color="#E18A8A" roughness={0.6} />
      </mesh>

      {/* Procedural Teeth */}
      {toothNumbers.map((t) => {
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

// Error Boundary / Fallback Wrapper Component
class GLTFBoundary extends React.Component<
  {
    children: React.ReactNode;
    fallback: React.ReactNode;
  },
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
  selectedTooth,
  onSelectTooth,
  onDoubleClickTooth,
  modelUrl = '/models/teeth-seperated.glb',
}) => {
  return (
    <div className="relative w-full h-full min-h-[450px] bg-gradient-to-b from-slate-950 via-slate-900 to-zinc-950 rounded-2xl overflow-hidden shadow-2xl border border-slate-800/80">
      <Canvas camera={{ position: [0, 1.5, 7.5], fov: 45 }} shadows className="w-full h-full">
        {/* Lighting setup */}
        <ambientLight intensity={0.8} />
        <directionalLight position={[5, 10, 7]} intensity={1.5} castShadow />
        <directionalLight position={[-5, -5, -5]} intensity={0.4} />
        <pointLight position={[0, 3, 2]} intensity={0.6} color="#ffffff" />

        {/* Auto-centering the perfectly scaled model */}
        <Center>
          <Suspense
            fallback={
              <Html center>
                <div className="text-white text-sm font-medium bg-slate-900/80 px-4 py-2 rounded-xl backdrop-blur-md border border-slate-700/60 shadow-xl whitespace-nowrap">
                  Loading 3D Model...
                </div>
              </Html>
            }
          >
              <GLTFBoundary
                fallback={
                  <ProceduralDentalArch
                    teethRecords={teethRecords}
                    selectedTooth={selectedTooth}
                    onSelectTooth={onSelectTooth}
                  />
                }
              >
                <GLTFDentalArch
                  modelUrl={modelUrl}
                  teethRecords={teethRecords}
                  selectedTooth={selectedTooth}
                  onSelectTooth={onSelectTooth}
                  onDoubleClickTooth={onDoubleClickTooth}
                />
              </GLTFBoundary>
            </Suspense>
        </Center>

        <OrbitControls enablePan={false} minDistance={2} maxDistance={15} autoRotate={false} makeDefault />
      </Canvas>

      {/* Floating Viewport Legend & Controls */}
      <div className="absolute top-4 left-4 pointer-events-none flex flex-col gap-1.5 bg-slate-900/80 backdrop-blur-md px-3.5 py-2.5 rounded-xl border border-slate-700/60 shadow-lg text-xs text-slate-200">
        <div className="flex items-center gap-2 font-semibold text-emerald-400">
          <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
          Interactive 3D Odontogram
        </div>
        <div className="text-[11px] text-slate-400">
          Rotate: Left Click + Drag | Zoom: Scroll Wheel
        </div>
      </div>
    </div>
  );
};

export default Dental3DViewer;
