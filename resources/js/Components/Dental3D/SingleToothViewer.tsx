import React, { useMemo } from 'react';
import { Canvas } from '@react-three/fiber';
import { OrbitControls, Center, useGLTF } from '@react-three/drei';
import * as THREE from 'three';
import { ToothRecord, CONDITION_COLORS } from './types';
import { extractToothNumber, isSoftTissueNode, isIgnoredNode } from './Dental3DViewer';

interface SingleToothViewerProps {
  modelUrl?: string;
  toothNumber: string;
  record?: ToothRecord;
}

const SingleToothMesh: React.FC<SingleToothViewerProps> = ({ modelUrl = '/models/teeth-seperated-optimized.glb', toothNumber, record }) => {
  const gltf = useGLTF(modelUrl, '/draco/') as any;
  
  const isolatedMesh = useMemo(() => {
    if (!gltf || !gltf.scene) return null;
    
    const toothGroup = new THREE.Group();

    // Traverse the original scene and find all parts (crown + roots) for this tooth
    gltf.scene.traverse((child: any) => {
      if (child.isMesh && !isIgnoredNode(child.name) && !isSoftTissueNode(child.name)) {
        const num = extractToothNumber(child.name);
        
        if (num === toothNumber) {
          const clonedMesh = child.clone(true) as THREE.Mesh;
          const condition = record?.condition || 'healthy';
          const isMissing = condition === 'missing';
          const isImplant = condition === 'implant';
          
          clonedMesh.material = new THREE.MeshStandardMaterial({
            color: CONDITION_COLORS[condition] || CONDITION_COLORS.healthy,
            roughness: isImplant ? 0.2 : 0.3,
            metalness: isImplant ? 0.85 : 0.05,
            transparent: isMissing,
            opacity: isMissing ? 0.2 : 1.0,
            wireframe: isMissing,
          });
          clonedMesh.material.map = null;
          toothGroup.add(clonedMesh);
        }
      }
    });

    if (toothGroup.children.length === 0) return null;
    return toothGroup;
  }, [gltf, toothNumber, record?.condition]);

  if (!isolatedMesh) return null;

  return (
    <Center>
      <primitive object={isolatedMesh} />
    </Center>
  );
};

export const SingleToothViewer: React.FC<SingleToothViewerProps> = (props) => {
  return (
    <div className="w-full h-full min-h-[300px] relative rounded-xl overflow-hidden bg-gradient-to-tr from-slate-950 via-slate-900 to-slate-950 border border-slate-700/50 shadow-inner">
      {/* RULE ENFORCEMENT: Highly tuned camera bounds to prevent zooming out into tiny meshes */}
      <Canvas camera={{ position: [0, 0, 0.35], fov: 15 }} shadows>
        <ambientLight intensity={1.2} />
        <directionalLight position={[5, 5, 5]} intensity={2} castShadow />
        <directionalLight position={[-5, -5, -5]} intensity={0.5} />
        
        <SingleToothMesh {...props} />
        
        {/* RULE ENFORCEMENT: Cinematic auto rotation with zoom bounds */}
        <OrbitControls 
          enablePan={false} 
          minDistance={0.05} 
          maxDistance={3} 
          autoRotate 
          autoRotateSpeed={3}
          makeDefault 
        />
      </Canvas>
      <div className="absolute top-2 left-3 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
        Isolated 3D View
      </div>
    </div>
  );
};
