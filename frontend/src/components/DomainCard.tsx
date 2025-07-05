import React from 'react';
import SparklineChange from './SparklineChange';

interface Props {
  domain: string;
  change: number[];
}

const DomainCard: React.FC<Props> = ({ domain, change }) => (
  <div className="p-4 bg-white rounded-xl shadow-md mb-4">
    <div className="flex justify-between items-center">
      <span className="font-semibold">{domain}</span>
      <SparklineChange data={change} />
    </div>
  </div>
);

export default DomainCard;
