import React from 'react';
import DomainCard from './DomainCard';

interface Props {
  data: { domain: string; change: number[] }[];
}

const TrendPanel: React.FC<Props> = ({ data }) => (
  <div className="space-y-2">
    {data.map((d) => (
      <DomainCard key={d.domain} domain={d.domain} change={d.change} />
    ))}
  </div>
);

export default TrendPanel;
