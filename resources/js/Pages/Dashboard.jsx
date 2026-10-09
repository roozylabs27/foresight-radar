import PrioritizingChart from "@/Components/PrioritizingChart";
import Radar from "@/Components/Radar";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Layout,
    theme,
    Tabs,
    DatePicker,
    Select,
    Card,
    Typography,
    Button,
    Tag,
    Tooltip,
} from "antd";
import {
    FilterOutlined,
    CalendarOutlined,
    AppstoreOutlined,
    DashboardOutlined,
    ReloadOutlined,
} from "@ant-design/icons";
import { useState } from "react";
import dayjs from "dayjs";
import OverallStatus from "@/Components/OverallStatus";

const { Title } = Typography;
const { Content } = Layout;
const { RangePicker } = DatePicker;

export default function Dashboard({ auth, dimensions }) {
    const {
        token: { borderRadiusLG },
    } = theme.useToken();

    const [defaultDate, setDefaultDate] = useState([
        dayjs().startOf("month"),
        dayjs().endOf("month"),
    ]);
    const [loading, setLoading] = useState(false);
    const [newDate, setNewDate] = useState(null);
    const [selectData, setSelectData] = useState(null);
    const [activeTab, setActiveTab] = useState("1");

    const items = [
        {
            key: "1",
            label: "Prioritizing",
            children: activeTab === "1" && (
                <PrioritizingChart
                    loading={loading}
                    setLoading={setLoading}
                    date={newDate}
                    selectData={selectData}
                    dimensions={dimensions}
                />
            ),
        },
        {
            key: "2",
            label: "Registered List",
            children: activeTab === "2" && (
                <OverallStatus
                    loading={loading}
                    setLoading={setLoading}
                    selectedData={selectData}
                    date={newDate}
                    permissions={auth?.permissions || []}
                />
            ),
        },
        {
            key: "3",
            label: "Foresight Radar",
            children: activeTab === "3" && (
                <Radar
                    loading={loading}
                    setLoading={setLoading}
                    date={newDate}
                    selectData={selectData}
                />
            ),
        },
    ];

    const handleTabsChange = (key) => {
        setLoading(true);
        setActiveTab(key);
    };

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
        setSelectData({
            [field]: value !== undefined ? value : null,
        });
    };

    const handleResetFilter = () => {
        const start = dayjs().startOf("month");
        const end = dayjs().endOf("month");
        setDefaultDate([start, end]);
        setNewDate({ date: [start.format("YYYY-MM-DD"), end.format("YYYY-MM-DD")] });
        setSelectData(null);
    };

    const isFiltered = !!selectData?.dimension || !!newDate;

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <DashboardOutlined style={{ color: "#1677ff", fontSize: 18 }} />
                    <Title level={4} style={{ margin: 0, color: "#1f1f1f" }}>
                        Dashboard
                    </Title>
                    <Tag
                        color="blue"
                        bordered={false}
                        style={{ fontSize: 11, fontWeight: 600, borderRadius: 10 }}
                    >
                        Executive Overview
                    </Tag>
                </div>
            }
        >
            <Head title="Dashboard" />

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
                            gap: 14,
                            flexWrap: "wrap",
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

                        {/* Periode Tanggal */}
                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <span
                                style={{
                                    fontSize: 12,
                                    color: "#6b7280",
                                    fontWeight: 500,
                                }}
                            >
                                <CalendarOutlined style={{ marginRight: 4 }} />
                                Periode:
                            </span>
                            <RangePicker
                                value={defaultDate}
                                disabled={loading}
                                onChange={handleRangePickerChange}
                                format="YYYY-MM-DD"
                                style={{ width: 230 }}
                                size="middle"
                            />
                        </div>

                        {/* Dimensi STEEP */}
                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <span
                                style={{
                                    fontSize: 12,
                                    color: "#6b7280",
                                    fontWeight: 500,
                                }}
                            >
                                <AppstoreOutlined style={{ marginRight: 4 }} />
                                Dimensi:
                            </span>
                            <Select
                                style={{ width: 210 }}
                                disabled={loading}
                                placeholder="Semua Dimensi"
                                filterOption={(input, option) =>
                                    (option?.label ?? "")
                                        .toLowerCase()
                                        .includes(input.toLowerCase())
                                }
                                allowClear
                                value={selectData?.dimension}
                                onChange={(value) =>
                                    handleSelectChange("dimension", value)
                                }
                                options={dimensions}
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
                    bodyStyle={{ padding: "12px 18px", flex: 1, display: "flex", flexDirection: "column" }}
                >
                    <Tabs
                        activeKey={activeTab}
                        items={items}
                        onChange={handleTabsChange}
                        style={{ flex: 1 }}
                    />
                </Card>
            </Content>
        </AuthenticatedLayout>
    );
}
