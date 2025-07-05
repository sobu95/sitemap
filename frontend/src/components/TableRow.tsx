import React from 'react';

interface Props {
  domain: string;
  status: string;
  pages: number;
}

const TableRow: React.FC<Props> = ({ domain, status, pages }) => (
  <tr className="border-b last:border-0">
    <td className="px-4 py-2 font-medium">{domain}</td>
    <td className="px-4 py-2 text-center">{status}</td>
    <td className="px-4 py-2 text-right">{pages}</td>
  </tr>
);

export default TableRow;
