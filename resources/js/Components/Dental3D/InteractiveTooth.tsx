import React, { useState } from 'react';
import { ThreeEvent } from '@react-three/fiber';
import * as THREE from 'three';
import { CONDITION_COLORS, ToothCondition } from './types';

interface InteractiveToothProps {
  toothNumber: string;
  geometry: THREE.BufferGeometry;
  material?: THREE.Material | THREE.Material[];
  condition?: ToothCondition;
  isSelected?: boolean;
  onSelect?: (toothNumber: string) => void;
  position?: [number, number, number];
  rotation?: [number, number, number];
  scale?: [number, number, number];
}

export const InteractiveTooth: React.FC<InteractiveToothProps> = ({
  toothNumber,
  geometry,
  material: originalMaterial,
  condition = 'healthy',
  isSelected = false,
  onSelect,
  position,
  rotation,
  scale,
}) => {
  const [hovered, setHovered] = useState(false);

  // Compute final dynamic color
  let colorHex = CONDITION_COLORS[condition] || CONDITION_COLORS.healthy;
  if (isSelected) {
    colorHex = '#10B981'; // Emerald
  } else if (hovered) {
    colorHex = '#60A5FA'; // Sky Blue
  }

  const isMissing = condition === 'missing';
  const isImplant = condition === 'implant';

  const handleClick = (e: ThreeEvent<MouseEvent>) => {
    e.stopPropagation();
    if (onSelect) {
      onSelect(toothNumber);
    }
  };

  const handlePointerOver = (e: ThreeEvent<PointerEvent>) => {
    e.stopPropagation();
    setHovered(true);
  };

  const handlePointerOut = (e: ThreeEvent<PointerEvent>) => {
    e.stopPropagation();
    setHovered(false);
  };

  return (
    <mesh
      geometry={geometry}
      position={position}
      rotation={rotation}
      scale={scale}
      onClick={handleClick}
      onPointerOver={handlePointerOver}
      onPointerOut={handlePointerOut}
      castShadow
      receiveShadow
    >
      <meshStandardMaterial
        color={colorHex}
        roughness={isImplant ? 0.2 : 0.4}
        metalness={isImplant ? 0.85 : 0.05}
        transparent={isMissing}
        opacity={isMissing ? 0.25 : 1.0}
        wireframe={isMissing}
      />
    </mesh>
  );
};

export default InteractiveTooth;
