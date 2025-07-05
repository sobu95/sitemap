import React from 'react';
import { LineChart, Line } from 'recharts';

interface Props {
  data: number[];
}

const SparklineChange: React.FC<Props> = ({ data }) => {
  const chartData = data.map((v, i) => ({ index: i, value: v }));
  return (
    <LineChart width={80} height={30} data={chartData}>
      <Line type="monotone" dataKey="value" stroke="#3b82f6" strokeWidth={2} dot={false} />
    </LineChart>
  );
};

export default SparklineChange;
