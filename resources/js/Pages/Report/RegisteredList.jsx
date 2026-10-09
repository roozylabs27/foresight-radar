import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Layout,
    theme,
    DatePicker,
    Select,
    Button,
    Tag,
    Tooltip,
    Card,
    Typography,
} from "antd";
import {
    FilterOutlined,
    CalendarOutlined,
    AppstoreOutlined,
    ClockCircleOutlined,
    FlagOutlined,
    CheckCircleOutlined,
    ReloadOutlined,
    UnorderedListOutlined,
} from "@ant-design/icons";
import { useState } from "react";
import dayjs from "dayjs";
import OverallStatus from "@/Components/OverallStatus";

const { Title } = Typography;
const { Content } = Layout;
const { RangePicker } = DatePicker;

export default function RegisteredList({
    auth,
    dimensions,
    title,
    priorities,
    status_actions,
    time_horizons,
}) {
    const {
        token: { borderRadiusLG },
    } = theme.useToken();

    const [defaultDate, setDefaultDate] = useState([
        dayjs().startOf("month"),
        dayjs().endOf("month"),
    ]);
    const [loading, setLoading] = useState(false);
    const [newDate, setNewDate] = useState(null);
    const [selectedData, setSelectedData] = useState({
        dimension: null,
        time_horizon: null,
        priority: null,
        status_action: null,
    });

    const handleRangePickerChange = (dates) => {
        if (dates && dates.length === 2) {
            const formattedDates = dates.map((date) =>
                date.format("YYYY-MM-DD")
            );

            setDefaultDate(dates);
            setNewDate({ date: formattedDates });
        } else {
            const newStartDate = dayjs().startOf("month").format("YYYY-MM-DD");
            const newEndDate = dayjs().endOf("month").format("YYYY-MM-DD");

            setDefaultDate([dayjs(newStartDate), dayjs(newEndDate)]);
            setNewDate({ date: [newStartDate, newEndDate] });
        }
    };

    const handleSelectChange = (field, value) => {
        setSelectedData((prev) => ({
            ...prev,
            [field]: value !== undefined ? value : null,
        }));
    };

    const handleResetFilter = () => {
        const start = dayjs().startOf("month");
        const end = dayjs().endOf("month");
        setDefaultDate([start, end]);
        setNewDate({ date: [start.format("YYYY-MM-DD"), end.format("YYYY-MM-DD")] });
        setSelectedData({
            dimension: null,
            time_horizon: null,
            priority: null,
            status_action: null,
        });
    };

    const isFiltered =
        !!newDate ||
        Object.values(selectedData).some((val) => val !== null && val !== undefined);

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <UnorderedListOutlined style={{ color: "#1677ff", fontSize: 18 }} />
                    <Title level={4} style={{ margin: 0, color: "#1f1f1f" }}>
                        {title}
                    </Title>
                    <Tag
                        color="blue"
                        bordered={false}
                        style={{ fontSize: 11, fontWeight: 600, borderRadius: 10 }}
                    >
                        Register & Audit Log
                    </Tag>
                </div>
            }
        >
            <Head title={title} />

            <Content
                style={{
                    padding: "12px 18px 8px",
                    display: "flex",
                    flexDirection: "column",
                    minHeight: "calc(100vh - 104px)",
                    boxSizing: "border-box",
                }}
            >
                {/* Compact Single-Row Filter Toolbar */}
                <div className="radar-filter-bar">
                    <div
                        style={{
                            display: "flex",
                            alignItems: "center",
                            gap: 12,
                            flexWrap: "wrap",
                            flex: 1,
                        }}
                    >
                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: 6,
                                color: "#1677ff",
                                fontWeight: 600,
                                fontSize: 13,
                            }}
                        >
                            <FilterOutlined />
                            <span>Filter Analisis:</span>
                        </div>

                        {/* Periode */}
                        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                            <span style={{ fontSize: 12, color: "#6b7280", fontWeight: 500 }}>
                                <CalendarOutlined style={{ marginRight: 2 }} />
                                Periode:
                            </span>
                            <RangePicker
                                value={defaultDate}
                                disabled={loading}
                                onChange={handleRangePickerChange}
                                format="YYYY-MM-DD"
                                style={{ width: 220 }}
                                size="middle"
                            />
                        </div>

                        {/* Dimensi */}
                        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                            <span style={{ fontSize: 12, color: "#6b7280", fontWeight: 500 }}>
                                <AppstoreOutlined style={{ marginRight: 2 }} />
                                Dimensi:
                            </span>
                            <Select
                                style={{ width: 165 }}
                                disabled={loading}
                                placeholder="Semua Dimensi"
                                value={selectedData?.dimension}
                                filterOption={(input, option) =>
                                    (option?.label ?? "").toLowerCase().includes(input.toLowerCase())
                                }
                                allowClear
                                onChange={(value) => handleSelectChange("dimension", value)}
                                options={dimensions}
                                size="middle"
                            />
                        </div>

                        {/* Time Horizon */}
                        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                            <span style={{ fontSize: 12, color: "#6b7280", fontWeight: 500 }}>
                                <ClockCircleOutlined style={{ marginRight: 2 }} />
                                Horizon:
                            </span>
                            <Select
                                style={{ width: 145 }}
                                disabled={loading}
                                placeholder="Semua Horizon"
                                value={selectedData?.time_horizon}
                                filterOption={(input, option) =>
                                    (option?.label ?? "").toLowerCase().includes(input.toLowerCase())
                                }
                                allowClear
                                onChange={(value) => handleSelectChange("time_horizon", value)}
                                options={time_horizons}
                                size="middle"
                            />
                        </div>

                        {/* Priority */}
                        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                            <span style={{ fontSize: 12, color: "#6b7280", fontWeight: 500 }}>
                                <FlagOutlined style={{ marginRight: 2 }} />
                                Prioritas:
                            </span>
                            <Select
                                style={{ width: 140 }}
                                disabled={loading}
                                placeholder="Semua Prioritas"
                                value={selectedData?.priority}
                                filterOption={(input, option) =>
                                    (option?.label ?? "").toLowerCase().includes(input.toLowerCase())
                                }
                                allowClear
                                onChange={(value) => handleSelectChange("priority", value)}
                                options={priorities}
                                size="middle"
                            />
                        </div>

                        {/* Status Action */}
                        <div style={{ display: "flex", alignItems: "center", gap: 6 }}>
                            <span style={{ fontSize: 12, color: "#6b7280", fontWeight: 500 }}>
                                <CheckCircleOutlined style={{ marginRight: 2 }} />
                                Status:
                            </span>
                            <Select
                                style={{ width: 140 }}
                                disabled={loading}
                                placeholder="Semua Status"
                                value={selectedData?.status_action}
                                filterOption={(input, option) =>
                                    (option?.label ?? "").toLowerCase().includes(input.toLowerCase())
                                }
                                allowClear
                                onChange={(value) => handleSelectChange("status_action", value)}
                                options={status_actions}
                                size="middle"
                            />
                        </div>
                    </div>

                    {isFiltered && (
                        <Tooltip title="Kembalikan filter ke kondisi awal">
                            <Button
                                type="text"
                                size="small"
                                icon={<ReloadOutlined />}
                                onClick={handleResetFilter}
                                style={{ color: "#ff4d4f", fontSize: 12 }}
                            >
                                Reset Filter
                            </Button>
                        </Tooltip>
                    )}
                </div>

                <Card
                    bordered={false}
                    style={{
                        borderRadius: borderRadiusLG,
                        boxShadow: "0 1px 3px rgba(0,0,0,0.05)",
                        flex: 1,
                        display: "flex",
                        flexDirection: "column",
                    }}
                    bodyStyle={{ padding: "16px 20px", flex: 1, display: "flex", flexDirection: "column" }}
                >
                    <OverallStatus
                        loading={loading}
                        setLoading={setLoading}
                        selectedData={selectedData}
                        permissions={auth?.permissions || []}
                        date={newDate}
                    />
                </Card>
            </Content>
        </AuthenticatedLayout>
    );
}
